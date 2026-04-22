<?php

namespace Juniyasyos\IamClient\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Juniyasyos\IamClient\Services\UserApplicationsService;
use Juniyasyos\IamClient\Support\IamConfig;

class IamInitiatedLogoutController extends Controller
{
    /**
     * Handle OP‑initiated (front‑channel) logout called by IAM.
     * Public endpoint — does NOT require authentication.
     */
    public function __invoke(Request $request, string $guard = 'web')
    {
        $currentUserId = auth()->id();

        Log::info('OP‑initiated logout received', [
            'session_id' => session()->getId(),
            'guard' => $guard,
            'auth_checked' => auth()->check(),
            'auth_user_id' => $currentUserId,
            'request_id' => $request->query('request_id') ?? $request->header('X-IAM-Request-Id'),
        ]);

        $guardName = IamConfig::guardName($guard);

        // Always perform full logout on OP‑initiated logout.
        UserApplicationsService::clearUserAppCache($currentUserId);
        UserApplicationsService::clearSessionAppCache();

        Auth::guard($guardName)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget('iam');

        Log::info('OP‑initiated logout: full_logout_performed', [
            'guard' => $guardName,
            'previous_user_id' => $currentUserId,
            'request_id' => $request->query('request_id') ?? $request->header('X-IAM-Request-Id'),
        ]);

        // Allow OP to pass `post_logout_redirect` so IAM can continue the
        // logout chain. Only accept redirects back to IAM (`IamConfig::baseUrl()`).
        $post = $request->query('post_logout_redirect');

        if ($post && str_starts_with((string) $post, IamConfig::baseUrl())) {
            return redirect($post);
        }

        $redirect = IamConfig::guardRedirect($guard);

        return redirect($redirect);
    }
}
