<?php

namespace Juniyasyos\IamClient\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Juniyasyos\IamClient\Exceptions\IamAuthenticationException;
use Juniyasyos\IamClient\Services\IamClientManager;
use Juniyasyos\IamClient\Support\IamConfig;

class SsoCallbackController extends Controller
{
    public function __construct(private readonly IamClientManager $manager) {}

    /**
     * Handle SSO callback from IAM server.
     *
     * Returns either a RedirectResponse (on success / intended redirect)
     * or a Response when rendering the callback view with an error.
     */
    public function __invoke(Request $request, string $guard = 'web'): Response|RedirectResponse
    {
        $token = $request->input('token') ?? $request->input('access_token');

        Log::info('SSO callback received', [
            'token' => $token ? 'present' : 'missing',
            'token_preview' => $token ? substr($token, 0, 10) . '...' : null,
            'session_id' => session()->getId(),
            'guard' => $guard,
            'request_ip' => $request->ip(),
            'request_path' => $request->path(),
        ]);

        if (! $token) {
            $errorCode = $request->input('error');
            $errorDescription = $request->input('error_description');
            $errorType = $request->input('error_type');
            $errorLocation = $request->input('error_location');

            if ($errorCode) {
                // Human readable reason in Bahasa Indonesia
                $friendly = match ($errorCode) {
                    'access_denied' => 'Akses ditolak oleh IAM karena user tidak memiliki hak atau role yang diperlukan.',
                    'invalid_token' => 'Token tidak valid, kemungkinan terputus/expired.',
                    'unauthorized_client' => 'Client tidak terdaftar atau tidak diizinkan.',
                    default => 'Terjadi kesalahan otentikasi SSO.',
                };

                $message = $errorDescription
                    ? sprintf('%s (%s)', $friendly, $errorDescription)
                    : $friendly;

                $context = compact('errorCode', 'errorType', 'errorLocation');

                Log::warning('SSO callback error redirect received', array_merge([
                    'message' => $message,
                    'session_id' => session()->getId(),
                    'guard' => $guard,
                    'request_ip' => $request->ip(),
                    'request_path' => $request->path(),
                ], $context));

                return response()->view('iam-client::callback-handler', [
                    'serverError' => $message,
                    'serverErrorContext' => $context,
                ], 403);
            }

            Log::warning('SSO callback token missing', ['request' => $request->all()]);
            abort(400, 'Missing token');
        }

        try {
            // Force full local session reset BEFORE setting authenticated user to
            // ensure we never reuse an old session when SSO identity changes.
            \Illuminate\Support\Facades\Auth::logout();
            session()->invalidate();
            session()->regenerate();
            session()->regenerateToken();

            $this->manager->loginWithToken($token, $guard);
        } catch (IamAuthenticationException $exception) {
            Log::warning('IAM authentication failed', [
                'message' => $exception->getMessage(),
                'context' => $exception->context,
                'guard' => $guard,
            ]);

            $message = 'Autentikasi gagal: ' . $exception->getMessage();

            return response()->view('iam-client::callback-handler', [
                'serverError' => $message,
                'serverErrorContext' => array_merge(
                    ['type' => class_basename($exception), 'code' => $exception->getCode()],
                    $exception->context ?? []
                ),
            ], 403);
        } catch (\Throwable $exception) {
            Log::error('Unexpected SSO callback error', [
                'message' => $exception->getMessage(),
                'guard' => $guard,
                'exception_class' => class_basename($exception),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
            ]);

            $message = 'Terjadi kesalahan tak terduga pada server. Silakan coba lagi atau hubungi admin.';

            return response()->view('iam-client::callback-handler', [
                'serverError' => $message,
                'serverErrorContext' => [
                    'error' => $exception->getMessage(),
                    'type' => class_basename($exception),
                ],
            ], 500);
        }

        $redirectTo = IamConfig::guardRedirect($guard);

        return redirect()->intended($redirectTo);
    }
}
