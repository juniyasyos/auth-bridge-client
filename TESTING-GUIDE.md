<?php

namespace Juniyasyos\IamClient\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Juniyasyos\IamClient\Services\UserApplicationsService;

/**
 * Example Test Cases for UserApplicationsService
 * 
 * These tests demonstrate how to test the service and its integration.
 * Copy and adapt to your application's test suite.
 * 
 * Location: tests/Feature/UserApplicationsServiceTest.php
 */
class UserApplicationsServiceTest
{
    /**
     * Test: Get applications with successful response.
     */
    public function test_get_applications_success()
    {
        // Setup session with IAM token
        Session::put('iam.access_token', 'test-token-123');

        // Mock HTTP response
        Http::fake([
            '*/api/users/applications' => Http::response([
                'sub' => '1',
                'user_id' => 1,
                'total_accessible_apps' => 1,
                'applications' => [
                    [
                        'id' => 1,
                        'app_key' => 'siimut',
                        'name' => 'SIIMUT',
                        'app_url' => 'http://127.0.0.1:8088',
                        'logo_url' => null,
                        'roles_count' => 1,
                    ],
                ],
                'accessible_apps' => ['siimut'],
                'timestamp' => '2026-04-01T00:00:00Z',
            ], 200),
        ]);

        $service = app(UserApplicationsService::class);
        $result = $service->getApplications();

        // Assertions
        assert($result['source'] === 'iam-server');
        assert($result['total_accessible_apps'] === 1);
        assert(count($result['applications']) === 1);
        assert($result['applications'][0]['app_key'] === 'siimut');
    }

    /**
     * Test: Handle missing token.
     */
    public function test_get_applications_missing_token()
    {
        // Don't set token in session
        Session::flush();

        $service = app(UserApplicationsService::class);
        $result = $service->getApplications();

        // Should return error
        assert(isset($result['error']));
        assert($result['error'] === 'iam_token_missing');
        assert($result['source'] === 'iam-error');
    }

    /**
     * Test: Handle server error response.
     */
    public function test_get_applications_server_error()
    {
        Session::put('iam.access_token', 'test-token-123');

        Http::fake([
            '*/api/users/applications' => Http::response(
                ['error' => 'unauthorized'],
                401
            ),
        ]);

        $service = app(UserApplicationsService::class);
        $result = $service->getApplications();

        // Should return error
        assert(isset($result['error']));
        assert($result['error'] === 'iam_server_error');
        assert($result['status'] === 401);
    }

    /**
     * Test: Get applications detail.
     */
    public function test_get_applications_detail()
    {
        Session::put('iam.access_token', 'test-token-123');

        Http::fake([
            '*/api/users/applications/detail' => Http::response([
                'sub' => '1',
                'user_id' => 1,
                'total_apps' => 1,
                'applications' => [
                    [
                        'id' => 1,
                        'app_key' => 'siimut',
                        'name' => 'SIIMUT',
                        'status' => 'active',
                        'metadata' => [
                            'logo' => [
                                'url' => 'https://...',
                                'available' => false,
                            ],
                            'urls' => [
                                'primary' => 'http://127.0.0.1:8088',
                                'callback' => 'http://127.0.0.1:8088/callback',
                            ],
                            'created_at' => '2026-04-01T00:00:00Z',
                        ],
                        'access_profiles_using_this_app' => [
                            ['id' => 1, 'name' => 'Super Admin', 'slug' => 'super-admin'],
                        ],
                    ],
                ],
                'user_profiles' => [],
                'timestamp' => '2026-04-01T00:00:00Z',
            ], 200),
        ]);

        $service = app(UserApplicationsService::class);
        $result = $service->getApplicationsDetail();

        assert(!isset($result['error']));
        assert($result['total_apps'] === 1);
        assert(isset($result['applications'][0]['metadata']));
        assert($result['applications'][0]['metadata']['logo']['available'] === false);
    }

    /**
     * Test: Get application by key.
     */
    public function test_get_application_by_key()
    {
        Session::put('iam.access_token', 'test-token-123');

        Http::fake([
            '*/api/users/applications' => Http::response([
                'applications' => [
                    ['app_key' => 'siimut', 'name' => 'SIIMUT'],
                    ['app_key' => 'other', 'name' => 'Other App'],
                ],
                'accessible_apps' => ['siimut', 'other'],
            ], 200),
        ]);

        $service = app(UserApplicationsService::class);

        $app = $service->getApplicationByKey('siimut');
        assert($app !== null);
        assert($app['app_key'] === 'siimut');

        $notFound = $service->getApplicationByKey('nonexistent');
        assert($notFound === null);
    }

    /**
     * Test: Debug methods.
     */
    public function test_debug_methods()
    {
        Session::put('iam.access_token', 'test-token-123');

        Http::fake([
            '*/api/users/applications' => Http::response(['applications' => []], 200),
            '*/api/users/applications/detail' => Http::response(['applications' => []], 200),
        ]);

        $service = app(UserApplicationsService::class);

        // Test basic debug
        $debug = $service->debugGetApplications();
        assert(isset($debug['status']));
        assert($debug['successful'] === true);

        // Test detail debug
        $detailDebug = $service->debugGetApplicationsDetail();
        assert(isset($detailDebug['status']));

        // Test all debug
        $allDebug = $service->debugAll();
        assert(isset($allDebug['timestamp']));
        assert(isset($allDebug['session']));
        assert(isset($allDebug['basic_endpoint']));
        assert(isset($allDebug['detail_endpoint']));
        assert(isset($allDebug['execution_time_ms']));
    }
}

/**
 * Integration Testing
 * 
 * To run actual integration tests against a real IAM server:
 * 
 * 1. Set environment variables:
 *    IAM_BASE_URL=http://localhost:8000
 *    IAM_TEST_TOKEN=real_iam_token_here
 * 
 * 2. Create real test:
 *    
 *    public function test_against_real_server()
 *    {
 *        $token = env('IAM_TEST_TOKEN');
 *        if (!$token) {
 *            $this->markTestSkipped('IAM test token not configured');
 *        }
 *        
 *        Session::put('iam.access_token', $token);
 *        $service = app(UserApplicationsService::class);
 *        $result = $service->getApplications();
 *        
 *        $this->assertFalse(isset($result['error']));
 *        $this->assertIsArray($result['applications']);
 *    }
 */

/**
 * Manual Testing via Artisan
 * 
 * # After logging in via IAM SSO:
 * 
 * php artisan iam:user-applications
 * php artisan iam:user-applications --detail
 * php artisan iam:user-applications --debug
 * php artisan iam:user-applications --all
 */

/**
 * Manual Testing via Tinker
 * 
 * php artisan tinker
 * 
 * >>> $service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
 * >>> $result = $service->getApplications();
 * >>> dd($result);
 */
