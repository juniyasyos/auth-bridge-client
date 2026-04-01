<?php

namespace Juniyasyos\IamClient\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Juniyasyos\IamClient\Support\IamConfig;

/**
 * Service for accessing user applications from IAM server.
 * 
 * Provides methods to fetch user's accessible applications with metadata:
 * - Basic applications list with roles
 * - Detailed applications with logos, URLs, and profile information
 * 
 * Usage:
 *   $service = app(UserApplicationsService::class);
 *   $apps = $service->getApplications();
 *   $detailedApps = $service->getApplicationsDetail();
 */
class UserApplicationsService
{
    /**
     * Get user's accessible applications with metadata.
     * 
     * Returns list of applications the user has access to, including:
     * - id, app_key, name, description
     * - status (active/inactive)
     * - logo_url - Application logo URL
     * - app_url - Primary application URL
     * - redirect_uris - All redirect URIs
     * - callback_url, backchannel_url
     * - roles - List of roles in each app
     * - roles_count - Number of roles
     * 
     * @return array
     * 
     * @example
     *   $apps = app(UserApplicationsService::class)->getApplications();
     *   // Returns:
     *   // [
     *   //     'source' => 'iam-server',
     *   //     'sub' => '1',
     *   //     'user_id' => 1,
     *   //     'total_accessible_apps' => 2,
     *   //     'applications' => [
     *   //         [
     *   //             'id' => 1,
     *   //             'app_key' => 'siimut',
     *   //             'name' => 'SIIMUT',
     *   //             'logo_url' => 'https://...',
     *   //             'app_url' => 'http://127.0.0.1:8088',
     *   //             'status' => 'active',
     *   //             ...
     *   //         ]
     *   //     ]
     *   // ]
     */
    public function getApplications(): array
    {
        return $this->fetchFromIam('/users/applications', 'applications');
    }

    /**
     * Get detailed user applications with complete metadata.
     * 
     * Returns comprehensive application information including:
     * - All metadata from basic endpoint
     * - Logo availability status
     * - All URL types (primary, redirects, callback, backchannel)
     * - Timestamps (created_at, updated_at)
     * - Access profiles that provide access to each app
     * - User's access profiles list
     * 
     * @return array
     * 
     * @example
     *   $appsDetail = app(UserApplicationsService::class)->getApplicationsDetail();
     *   // Returns:
     *   // [
     *   //     'source' => 'iam-server',
     *   //     'sub' => '1',
     *   //     'user_id' => 1,
     *   //     'total_apps' => 2,
     *   //     'applications' => [
     *   //         [
     *   //             'id' => 1,
     *   //             'app_key' => 'siimut',
     *   //             'name' => 'SIIMUT',
     *   //             'metadata' => [
     *   //                 'logo' => ['url' => '...', 'available' => false],
     *   //                 'urls' => [...],
     *   //                 'created_at' => '2026-04-01T...',
     *   //             ],
     *   //             'access_profiles_using_this_app' => [
     *   //                 ['id' => 1, 'name' => 'Super Admin', 'slug' => 'super-admin']
     *   //             ],
     *   //             ...
     *   //         ]
     *   //     ],
     *   //     'user_profiles' => [...]
     *   // ]
     */
    public function getApplicationsDetail(): array
    {
        return $this->fetchFromIam('/users/applications/detail', 'applicationsDetail');
    }

    /**
     * Get applications for a specific app (filter if app_key exists).
     * 
     * @param string|null $appKey Filter by app_key (optional)
     * @return array|null Matching app or null if not found
     */
    public function getApplicationByKey(?string $appKey = null): ?array
    {
        if (empty($appKey)) {
            return null;
        }

        $apps = $this->getApplications();

        if (isset($apps['applications'])) {
            foreach ($apps['applications'] as $app) {
                if (isset($app['app_key']) && $app['app_key'] === $appKey) {
                    return $app;
                }
            }
        }

        return null;
    }

    /**
     * Debug: Get raw HTTP response for applications endpoint.
     * 
     * Useful for debugging response structure and status.
     * 
     * @return array Debug information including status, headers, body
     */
    public function debugGetApplications(): array
    {
        return $this->debugFetch('/users/applications', 'BasicApplications');
    }

    /**
     * Debug: Get raw HTTP response for applications detail endpoint.
     * 
     * @return array Debug information including status, headers, body
     */
    public function debugGetApplicationsDetail(): array
    {
        return $this->debugFetch('/users/applications/detail', 'ApplicationsDetail');
    }

    /**
     * Debug: Get comprehensive debugging information.
     * 
     * Returns combined info about both endpoints, token status, and timing.
     * 
     * @return array
     * 
     * @example
     *   $debug = app(UserApplicationsService::class)->debugAll();
     *   dd($debug);
     */
    public function debugAll(): array
    {
        $startTime = microtime(true);

        return [
            'timestamp' => now()->toIso8601String(),
            'session' => [
                'id' => session()->getId(),
                'has_iam_token' => !empty(session('iam.access_token')),
                'has_iam_sub' => !empty(session('iam.sub')),
                'iam_sub' => session('iam.sub'),
                'iam_app' => session('iam.app'),
            ],
            'endpoints' => [
                'base_url' => IamConfig::baseUrl(),
                'applications' => IamConfig::baseUrl() . '/api/users/applications',
                'applications_detail' => IamConfig::baseUrl() . '/api/users/applications/detail',
            ],
            'basic_endpoint' => $this->debugGetApplications(),
            'detail_endpoint' => $this->debugGetApplicationsDetail(),
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ];
    }

    /**
     * Internal: Fetch data from IAM API with fallback handling.
     * 
     * @param string $endpoint API endpoint path (e.g., '/users/applications')
     * @param string $debugName Name for logging
     * @return array
     */
    private function fetchFromIam(string $endpoint, string $debugName): array
    {
        $iamAccessToken = session('iam.access_token') ?? session('iam.access_token_backup');

        if (empty($iamAccessToken)) {
            return $this->errorResponse('iam_token_missing', 'IAM access token not found in session', $endpoint);
        }

        try {
            $url = IamConfig::baseUrl() . '/api' . $endpoint;

            Log::info("UserApplicationsService: Fetching {$debugName}", [
                'session_id' => session()->getId(),
                'endpoint' => $url,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $iamAccessToken,
                'Accept' => 'application/json',
            ])->timeout(10)->get($url);

            if ($response->successful()) {
                $payload = $response->json();
                Log::info("UserApplicationsService: {$debugName} fetched successfully", [
                    'status' => $response->status(),
                    'session_id' => session()->getId(),
                ]);

                // Add source indicator
                return array_merge(['source' => 'iam-server'], (array) $payload);
            }

            Log::warning("UserApplicationsService: {$debugName} request failed", [
                'endpoint' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'session_id' => session()->getId(),
            ]);

            return $this->errorResponse(
                'iam_server_error',
                "IAM server returned {$response->status()}",
                $endpoint,
                ['status' => $response->status(), 'body' => $response->body()]
            );
        } catch (\Throwable $e) {
            Log::error("UserApplicationsService: Exception calling IAM server", [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'session_id' => session()->getId(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'iam_request_error',
                $e->getMessage(),
                $endpoint
            );
        }
    }

    /**
     * Internal: Debug fetch with raw response details.
     * 
     * @param string $endpoint API endpoint path
     * @param string $name Debug name
     * @return array
     */
    private function debugFetch(string $endpoint, string $name): array
    {
        $iamAccessToken = session('iam.access_token');
        $hasToken = !empty($iamAccessToken);

        if (!$hasToken) {
            return [
                'name' => $name,
                'status' => 'error',
                'error' => 'No IAM token in session',
                'token_present' => false,
            ];
        }

        try {
            $url = IamConfig::baseUrl() . '/api' . $endpoint;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $iamAccessToken,
                'Accept' => 'application/json',
            ])->timeout(10)->get($url);

            return [
                'name' => $name,
                'url' => $url,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'headers' => [
                    'content-type' => $response->header('content-type'),
                    'date' => $response->header('date'),
                ],
                'body_size' => strlen($response->body()),
                'body' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'status' => 'error',
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ];
        }
    }

    /**
     * Internal: Format error response.
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param string $endpoint Endpoint that failed
     * @param array $extra Extra debug data
     * @return array
     */
    private function errorResponse(string $code, string $message, string $endpoint, array $extra = []): array
    {
        return array_merge([
            'source' => 'iam-error',
            'error' => $code,
            'message' => $message,
            'endpoint' => $endpoint,
            'session_id' => session()->getId(),
        ], $extra);
    }
}
