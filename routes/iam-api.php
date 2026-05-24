<?php

use Illuminate\Support\Facades\Route;
use Juniyasyos\IamClient\Http\Controllers\SyncRolesController;
use Juniyasyos\IamClient\Support\IamConfig;

/*
|--------------------------------------------------------------------------
| IAM Client API Routes
|--------------------------------------------------------------------------
|
| These routes expose IAM synchronization and health endpoints.
|
*/

$middleware = ['api'];
if (config('iam.backchannel_verify', true)) {
    $middleware[] = 'iam.backchannel.verify';
}

Route::middleware($middleware)->group(function () {
    if (IamConfig::syncUsersEnabled()) {
        Route::get('/api/iam/sync-users', \Juniyasyos\IamClient\Http\Controllers\SyncUsersController::class)
            ->name('iam.sync-users');
    }

    Route::get('/api/iam/sync-roles', SyncRolesController::class)
        ->name('iam.sync-roles');

    Route::post('/api/iam/push-roles', \Juniyasyos\IamClient\Http\Controllers\PushRolesController::class)
        ->name('iam.push-roles');

    Route::post('/api/iam/push-users', \Juniyasyos\IamClient\Http\Controllers\PushUsersController::class)
        ->name('iam.push-users');
});

Route::prefix('api/manage-unit-kerja')->group(function () {
    Route::get('/center/provision', \Juniyasyos\IamClient\Http\Controllers\CenterSyncController::class)
        ->name('iam.unit-kerja.center.provision');

    Route::post('/client/sync', \Juniyasyos\IamClient\Http\Controllers\ClientSyncController::class)
        ->name('iam.unit-kerja.client.sync');

    Route::post(config('iam.unit_kerja.push.path', 'client/push'), \Juniyasyos\IamClient\Http\Controllers\ClientPushUnitKerjaController::class)
        ->middleware(config('iam.unit_kerja.push.middleware', ['api']))
        ->name('iam.unit-kerja.client.push');
});

Route::get('/api/iam/health', \Juniyasyos\IamClient\Http\Controllers\HealthController::class)
    ->name('iam.health');
