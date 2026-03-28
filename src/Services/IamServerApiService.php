<?php

namespace Juniyasyos\IamClient\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Juniyasyos\IamClient\Support\IamConfig;

class IamServerApiService
{
    public function getUserApplications(): array
    {
        $token = session('iam.access_token');

        if (empty($token)) {
            Log::warning('IamServerApiService: no IAM access token in session');
            return [];
        }

        $endpoint = IamConfig::userApplicationsEndpoint();

        $response = Http::timeout(10)
            ->withToken($token)
            ->acceptJson()
            ->get($endpoint);

        if (! $response->successful()) {
            Log::error('IamServerApiService failed to load user applications from IAM', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        return $response->json() ?? [];
    }
}
