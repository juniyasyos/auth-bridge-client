<?php

namespace Juniyasyos\IamClient\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Juniyasyos\IamClient\Support\TokenValidator;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['status' => 'ok', 'message' => 'healthy (unauthenticated)'], 200);
        }

        try {
            $payload = TokenValidator::decode($token);

            return response()->json([
                'status' => 'ok',
                'message' => 'healthy (authenticated)',
                'app_key' => $payload->app_key ?? null,
                'token_type' => $payload->type ?? null,
                'expires_at' => $payload->exp ?? null,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'invalid_token', 'error' => $e->getMessage()], 401);
        }
    }
}
