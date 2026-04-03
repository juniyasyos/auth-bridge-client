<?php

namespace Juniyasyos\IamClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Juniyasyos\IamClient\Services\UserApplicationsService;
use Juniyasyos\IamClient\Support\IamConfig;

/**
 * Verify IAM access token on every request when enabled via config.
 * - Respects config('iam.verify_each_request') to enable/disable the check.
 * - Does NOT log out on transient verification errors (network/timeouts).
 */
class VerifyIamToken
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('iam.verify_each_request', true)) {
            return $next($request);
        }

        $accessToken = $request->session()->get('iam.access_token')
            ?? $request->session()->get('iam.access_token_backup');

        if (empty($accessToken)) {
            // Strict mode: if user is authenticated by IAM payload but token is missing,
            // force relogin to avoid stale web-session without valid IAM token.
            if (Auth::check() && $request->session()->has('iam.sub')) {
                $userId = Auth::id();

                Log::warning('IamClient::VerifyIamToken - authenticated session without IAM token; clearing session', [
                    'session_id' => $request->session()->getId(),
                    'user_id' => $userId,
                ]);

                // Clear application cache
                UserApplicationsService::clearUserAppCache($userId);
                UserApplicationsService::clearSessionAppCache();

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->forget('iam');

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['message' => 'Session expired, please login again.'], 401);
                }

                $loginRoute = IamConfig::loginRouteName(config('iam.guard', 'web'));

                if (\Illuminate\Support\Facades\Route::has($loginRoute)) {
                    return redirect()->route($loginRoute)->with('warning', 'Session expired, please login again.');
                }

                return redirect()->to(config('iam.login_route', '/sso/login'))->with('warning', 'Session expired, please login again.');
            }

            return $next($request);
        }

        // Prefer local JWT verification to avoid network roundtrips
        try {
            $payload = \Juniyasyos\IamClient\Support\TokenValidator::decode($accessToken);

            if (Cache::has('iam.user_logout.' . ($payload->sub ?? null))) {
                $userId = $payload->sub ?? null;

                Log::warning('IamClient::VerifyIamToken - user logged out via backchannel', [
                    'user_id' => $userId,
                    'session_id' => $request->session()->getId(),
                ]);

                // Clear application cache
                UserApplicationsService::clearUserAppCache($userId);
                UserApplicationsService::clearSessionAppCache();

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->forget('iam');

                return redirect()->route(IamConfig::loginRouteName(config('iam.guard', 'web')))
                    ->with('warning', 'Your session was revoked by IAM. Please login again.');
            }

            // keep session payload in sync
            $request->session()->put('iam.payload', (array) $payload);
            $request->session()->put('iam.sub', $payload->sub ?? null);
        } catch (\Throwable $e) {
            Log::warning('IamClient::VerifyIamToken - token invalid, attempting silent refresh', [
                'error' => $e->getMessage(),
                'session_id' => $request->session()->getId(),
            ]);

            // Try to silently refresh the token before logging out
            $refreshedToken = $this->attemptSilentRefresh($accessToken);
            if ($refreshedToken) {
                // Update session with new token and continue request
                $request->session()->put('iam.access_token', $refreshedToken);
                try {
                    $payload = \Juniyasyos\IamClient\Support\TokenValidator::decode($refreshedToken);
                    $request->session()->put('iam.payload', (array) $payload);
                    $request->session()->put('iam.sub', $payload->sub ?? null);

                    Log::info('IamClient::VerifyIamToken - silent token refresh successful', [
                        'session_id' => $request->session()->getId(),
                    ]);

                    return $next($request);
                } catch (\Throwable $decodeErr) {
                    Log::warning('IamClient::VerifyIamToken - refreshed token decode failed', [
                        'error' => $decodeErr->getMessage(),
                    ]);
                }
            }

            // If refresh failed, logout and redirect
            Log::warning('IamClient::VerifyIamToken - silent refresh failed, clearing session', [
                'error' => $e->getMessage(),
                'session_id' => $request->session()->getId(),
            ]);

            $userId = Auth::id();
            UserApplicationsService::clearUserAppCache($userId);
            UserApplicationsService::clearSessionAppCache();

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->forget('iam');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Session expired, please login again.'], 401);
            }

            $loginRoute = IamConfig::loginRouteName(config('iam.guard', 'web'));

            if (\Illuminate\Support\Facades\Route::has($loginRoute)) {
                return redirect()->route($loginRoute)->with('warning', 'Session expired, please login again.');
            }

            return redirect()->to(config('iam.login_route', '/sso/login'))->with('warning', 'Session expired, please login again.');
        }

        // Remote verification (serves on-server logout expiration/revoke)
        if (config('iam.verify_remote_each_request', true)) {
            try {
                $verifyResponse = Http::timeout(4)->post(IamConfig::verifyEndpoint(), [
                    'token' => $accessToken,
                    'include_user_data' => false,
                ]);

                if (! $verifyResponse->successful()) {
                    throw new \Exception('Remote verify returned non-200');
                }
            } catch (\Throwable $remoteException) {
                Log::warning('IamClient::VerifyIamToken - remote verify failure, clearing session', [
                    'error' => $remoteException->getMessage(),
                    'session_id' => $request->session()->getId(),
                ]);

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->forget('iam');

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['message' => 'Session invalidated by IAM, please login again.'], 401);
                }

                $loginRoute = IamConfig::loginRouteName(config('iam.guard', 'web'));

                if (\Illuminate\Support\Facades\Route::has($loginRoute)) {
                    return redirect()->route($loginRoute)->with('warning', 'Session invalidated by IAM, please login again.');
                }

                return redirect()->to(config('iam.login_route', '/sso/login'))->with('warning', 'Session invalidated by IAM, please login again.');
            }
        }

        return $next($request);
    }

    /**
     * Attempt to silently refresh the expired token.
     * Returns refreshed token on success, null on failure.
     */
    private function attemptSilentRefresh(?string $expiredToken): ?string
    {
        if (empty($expiredToken)) {
            return null;
        }

        try {
            $response = Http::timeout(3)->post(IamConfig::refreshTokenEndpoint(), [
                'token' => $expiredToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    return $data['access_token'];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('IamClient::VerifyIamToken - silent refresh request failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
