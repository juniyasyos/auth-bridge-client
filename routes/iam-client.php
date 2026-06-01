<?php

use Illuminate\Support\Facades\Route;
use Juniyasyos\IamClient\Http\Controllers\SsoLoginRedirectController;
use Juniyasyos\IamClient\Http\Controllers\SsoCallbackController;
use Juniyasyos\IamClient\Http\Controllers\LogoutController;
use Juniyasyos\IamClient\Http\Controllers\IamInitiatedLogoutController;

/*
|--------------------------------------------------------------------------
| IAM Client Routes
|--------------------------------------------------------------------------
|
| These routes handle SSO login flow with IAM server.
|
*/

Route::middleware('web')->group(function () {
    // Redirect to IAM login page
    Route::get(config('iam.login_route', '/sso/login'), SsoLoginRedirectController::class)
        ->name('iam.sso.login')
        ->defaults('guard', 'web');

    // Handle callback from IAM after successful authentication
    Route::match(['GET', 'POST'], config('iam.callback_route', '/sso/callback'), SsoCallbackController::class)
        ->name('iam.sso.callback')
        ->defaults('guard', 'web');

    // Logout
    Route::post('/logout', LogoutController::class)
        ->name('iam.logout')
        ->defaults('guard', 'web');

    // Public endpoint for OP‑initiated (global) logout called by IAM.
    // Clears only IAM-related session keys so client can sign-out silently.
    Route::get('/iam/logout', IamInitiatedLogoutController::class)
        ->name('iam.iam.logout')
        ->defaults('guard', 'web');

    // Fetch current user's IAM applications from IAM server.
    // Requires that the user is already authenticated and has a valid token in session.
    Route::get('/iam/user-applications', \Juniyasyos\IamClient\Http\Controllers\IamUserApplicationsController::class)
        ->name('iam.user-applications')
        ->middleware(['iam.verify'])
        ->defaults('guard', 'web');

    // Debug route: Web-only access to local user app list (no Bearer token needed).
    // Anda bisa buka di browser setelah login dengan user yang valid.
    Route::get('/iam/debug/user-applications', [\Juniyasyos\IamClient\Http\Controllers\IamUserApplicationsController::class, 'webUserApplications'])
        ->name('iam.user-applications.debug')
        ->middleware(['web', 'auth'])
        ->defaults('guard', 'web');
});

