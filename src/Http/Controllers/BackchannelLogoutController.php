<?php

namespace Juniyasyos\IamClient\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Juniyasyos\IamClient\Services\UserApplicationsService;

class BackchannelLogoutController extends Controller
{
    /**
     * Handle OP → client back‑channel logout (server→server).
     * Public endpoint; signature verification is done by middleware.
     */
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'event' => 'required|string|in:logout',
            'user.id' => 'required',
        ]);

        $userId = data_get($data, 'user.id');

        // Best‑effort: delete sessions for various supported drivers.
        try {
            $driver = config('session.driver');

            if ($driver === 'database') {
                $deleted = DB::table('sessions')
                    ->where('payload', 'like', '%"user_id";i:' . $userId . '%')
                    ->delete();

                Log::info('iam.client.backchannel_session_cleanup', [
                    'user_id' => $userId,
                    'deleted_sessions' => $deleted,
                    'driver' => $driver,
                ]);
            }

            if ($driver === 'file') {
                $files = glob(storage_path('framework/sessions/*')) ?: [];

                $deleted = 0;
                foreach ($files as $file) {
                    if (! is_file($file)) {
                        continue;
                    }

                    if (str_contains(file_get_contents($file), '"user_id";i:' . $userId)) {
                        unlink($file);
                        $deleted++;
                    }
                }

                Log::info('iam.client.backchannel_session_cleanup', [
                    'user_id' => $userId,
                    'deleted_sessions' => $deleted,
                    'driver' => $driver,
                ]);
            }

            if (
                in_array($driver, ['redis', 'memcached']) &&
                (Cache::getStore() instanceof \Illuminate\Cache\RedisStore || Cache::getStore() instanceof \Illuminate\Cache\MemcachedStore)
            ) {
                $pattern = '*';
                try {
                    $keys = Redis::keys(config('cache.prefix') . ':*');
                } catch (\Throwable $e) {
                    $keys = [];
                }

                $deleted = 0;
                foreach ($keys as $key) {
                    $val = Redis::get($key);
                    if ($val && str_contains($val, '"user_id";i:' . $userId)) {
                        Redis::del($key);
                        $deleted++;
                    }
                }

                Log::info('iam.client.backchannel_session_cleanup', [
                    'user_id' => $userId,
                    'deleted_sessions' => $deleted,
                    'driver' => $driver,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('iam.client.backchannel_session_cleanup_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }

        // Best‑effort: revoke tokens on local user (supports Sanctum/Passport via tokens() relation)
        $userModel = config('iam.user_model', 'App\\Models\\User');
        $revokedTokens = null;

        if (class_exists($userModel)) {
            try {
                $user = $userModel::find($userId) ?: $userModel::where('iam_id', $userId)->first();

                if ($user && method_exists($user, 'tokens')) {
                    $revokedTokens = $user->tokens()->delete();

                    Log::info('iam.client.backchannel_revoke', [
                        'user_id' => $userId,
                        'revoked_tokens' => $revokedTokens,
                        'request_id' => $request->header('X-IAM-Request-Id'),
                    ]);
                } else {
                    Log::info('iam.client.backchannel_revoke_skipped', ['user_id' => $userId]);
                }
            } catch (\Throwable $e) {
                Log::warning('iam.client.backchannel_revoke_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }

        Log::info('iam.client.backchannel_logout_processed', [
            'user_id' => $userId,
            'revoked_tokens' => $revokedTokens,
            'request_id' => $request->header('X-IAM-Request-Id'),
        ]);

        // Mark user as invalidated so per-request verification middleware can kick out still-active sessions.
        Cache::put('iam.user_logout.' . $userId, true, now()->addMinutes(30));

        // Clear application cache for the user
        UserApplicationsService::clearUserAppCache($userId);

        // If this request belongs to an authenticated session of the same user,
        // reset it immediately.
        if (optional(auth()->user())->getAuthIdentifier() == $userId) {
            auth()->logout();
            Session::invalidate();
            Session::regenerateToken();
            UserApplicationsService::clearSessionAppCache();
        }

        return response()->json(['ok' => true]);
    }
}
