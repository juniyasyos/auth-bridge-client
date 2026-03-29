<?php

namespace Juniyasyos\IamClient\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Juniyasyos\IamClient\Support\IamConfig;

class IamServerApiService
{
    public function getUserApplications(): array
    {
        // Always use IAM server for user applications in client plugin mode.
        $iamAccessToken = session('iam.access_token') ?? session('iam.access_token_backup');

        if (! empty($iamAccessToken)) {
            try {
                $endpoint = IamConfig::userApplicationsEndpoint();
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $iamAccessToken,
                    'Accept' => 'application/json',
                ])->timeout(10)->get($endpoint);

                if ($response->successful()) {
                    $payload = $response->json();
                    Log::info('IamServerApiService: fetched user applications from IAM server', [
                        'session_id' => session()->getId(),
                        'endpoint' => $endpoint,
                    ]);

                    // Normalize result (source plus server payload).
                    return array_merge(['source' => 'iam-server'], (array) $payload);
                }

                Log::warning('IamServerApiService: IAM server user applications request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('IamServerApiService exception calling IAM server', [
                    'message' => $e->getMessage(),
                    'session_id' => session()->getId(),
                ]);
            }
        }

        // Fallback: IAM session may exist but token missing or fetch failed.
        if (! empty(session('iam.sub')) && ! empty(session('iam.app'))) {
            Log::info('IamServerApiService: iam-session-fallback (tokenless mode)', [
                'session_id' => session()->getId(),
                'iam_sub' => session('iam.sub'),
                'iam_app' => session('iam.app'),
            ]);

            return [
                'source' => 'iam-session-fallback',
                'applications' => [],
                'hint' => 'IAM session exists without token or server response failed. Re-login if full app list required.',
            ];
        }

        return [
            'error' => 'iam_auth_missing',
            'message' => 'Authenticated user or IAM session not available. Please login via SSO.',
        ];
    }
}
