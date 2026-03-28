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
}
