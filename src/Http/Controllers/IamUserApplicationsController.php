<?php

namespace Juniyasyos\IamClient\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Juniyasyos\IamClient\Services\IamServerApiService;

class IamUserApplicationsController extends Controller
{
    public function __construct(private readonly IamServerApiService $iamApi) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = [];

        try {
            $data = $this->iamApi->getUserApplications();
        } catch (\Throwable $e) {
            Log::error('IamUserApplicationsController exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Unable to fetch IAM user applications'], 500);
        }

        return response()->json($data);
    }

    /**
     * Debug route: web-only, no Bearer token needed.
     */
    public function webUserApplications(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! method_exists($user, 'accessibleApps')) {
            return response()->json(['message' => 'User model does not implement accessibleApps'], 500);
        }

        return response()->json([
            'source' => 'local-user',
            'sub' => (string) $user->id,
            'user_id' => $user->id,
            'applications' => $user->accessibleApps(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
