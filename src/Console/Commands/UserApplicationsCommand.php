<?php

namespace Juniyasyos\IamClient\Console\Commands;

use Illuminate\Console\Command;
use Juniyasyos\IamClient\Services\UserApplicationsService;

/**
 * Example/Testing command for UserApplicationsService.
 * 
 * This command demonstrates how to use the UserApplicationsService
 * to fetch user applications from the IAM server.
 * 
 * Usage:
 *   php artisan iam:user-applications                 # Fetch basic apps
 *   php artisan iam:user-applications --detail        # Fetch detailed apps
 *   php artisan iam:user-applications --debug         # Show debug info
 *   php artisan iam:user-applications --all           # Full debug output
 */
class UserApplicationsCommand extends Command
{
    protected $signature = 'iam:user-applications {--detail : Show detailed application information} {--debug : Show debug information} {--all : Show complete debug output}';

    protected $description = 'Fetch and display user accessible applications from IAM server';

    public function handle(): int
    {
        $service = app(UserApplicationsService::class);

        try {
            if ($this->option('all')) {
                $this->showAllDebug($service);
            } elseif ($this->option('debug')) {
                $this->showDebug($service);
            } elseif ($this->option('detail')) {
                $this->showDetailedApplications($service);
            } else {
                $this->showBasicApplications($service);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display basic applications list.
     */
    private function showBasicApplications(UserApplicationsService $service): void
    {
        $this->info('📱 Fetching user applications...');

        $result = $service->getApplications();

        $this->line('');
        $this->info('Response Source: ' . ($result['source'] ?? 'unknown'));

        if (isset($result['error'])) {
            $this->error('Error: ' . $result['error']);
            $this->line('Message: ' . ($result['message'] ?? 'Unknown error'));
            return;
        }

        $this->info('User ID: ' . ($result['user_id'] ?? 'unknown'));
        $this->info('Total Accessible Apps: ' . ($result['total_accessible_apps'] ?? 0));
        $this->line('');

        if (empty($result['applications'])) {
            $this->warn('No applications found');
            return;
        }

        foreach ($result['applications'] as $app) {
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📦 ' . ($app['name'] ?? 'Unknown'));
            $this->line('');
            $this->line('  App Key:        ' . ($app['app_key'] ?? '-'));
            $this->line('  Status:         ' . ($app['status'] ?? '-'));
            $this->line('  Primary URL:    ' . ($app['app_url'] ?? '-'));
            $this->line('  Has Logo:       ' . ($app['has_logo'] ? '✓ Yes' : '✗ No'));
            $this->line('  Roles Count:    ' . ($app['roles_count'] ?? 0));

            if (!empty($app['roles'])) {
                $this->line('  Roles:');
                foreach ($app['roles'] as $role) {
                    $this->line('    - ' . $role['name'] . ' (' . $role['slug'] . ')');
                }
            }
        }

        $this->line('');
        $this->info('✓ Command completed successfully');
    }

    /**
     * Display detailed applications with metadata.
     */
    private function showDetailedApplications(UserApplicationsService $service): void
    {
        $this->info('📱 Fetching detailed application information...');

        $result = $service->getApplicationsDetail();

        $this->line('');
        $this->info('Response Source: ' . ($result['source'] ?? 'unknown'));

        if (isset($result['error'])) {
            $this->error('Error: ' . $result['error']);
            return;
        }

        $this->info('Total Apps: ' . ($result['total_apps'] ?? 0));
        $this->line('');

        if (empty($result['applications'])) {
            $this->warn('No applications found');
            return;
        }

        foreach ($result['applications'] as $app) {
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📦 ' . ($app['name'] ?? 'Unknown'));
            $this->line('');

            // Basic info
            $this->line('  ID:             ' . ($app['id'] ?? '-'));
            $this->line('  App Key:        ' . ($app['app_key'] ?? '-'));
            $this->line('  Status:         ' . ($app['status'] ?? '-'));

            // Logo info
            if (isset($app['metadata']['logo'])) {
                $logo = $app['metadata']['logo'];
                $this->line('  Logo:           ' . ($logo['available'] ? '✓ Available' : '✗ Not available'));
                if ($logo['available']) {
                    $this->line('    URL: ' . $logo['url']);
                }
            }

            // URLs
            if (isset($app['metadata']['urls'])) {
                $this->line('  URLs:');
                $urls = $app['metadata']['urls'];
                $this->line('    Primary:      ' . ($urls['primary'] ?? '-'));
                if (!empty($urls['callback'])) {
                    $this->line('    Callback:     ' . $urls['callback']);
                }
                if (!empty($urls['backchannel'])) {
                    $this->line('    Backchannel:  ' . $urls['backchannel']);
                }
                if (!empty($urls['all_redirects'])) {
                    $this->line('    Redirects:    ' . implode(', ', $urls['all_redirects']));
                }
            }

            // Timestamps
            if (isset($app['metadata']['created_at'])) {
                $this->line('  Created:        ' . $app['metadata']['created_at']);
                $this->line('  Updated:        ' . ($app['metadata']['updated_at'] ?? '-'));
            }

            // Roles
            $this->line('  Roles Count:    ' . ($app['roles_count'] ?? 0));
            if (!empty($app['roles'])) {
                $this->line('  Roles:');
                foreach ($app['roles'] as $role) {
                    $this->line('    - ' . $role['name'] . ' (' . $role['slug'] . ')');
                }
            }

            // Access profiles
            if (!empty($app['access_profiles_using_this_app'])) {
                $this->line('  Access Profiles:');
                foreach ($app['access_profiles_using_this_app'] as $profile) {
                    $this->line('    - ' . $profile['name'] . ' (' . $profile['slug'] . ')');
                }
            }
        }

        $this->line('');
        $this->info('✓ Command completed successfully');
    }

    /**
     * Display debug information for both endpoints.
     */
    private function showDebug(UserApplicationsService $service): void
    {
        $this->info('🔍 Fetching debug information...');
        $this->line('');

        // Basic endpoint
        $this->warn('═══ Basic Endpoint: /api/users/applications ═══');
        $debug = $service->debugGetApplications();
        $this->displayDebugInfo($debug);

        $this->line('');

        // Detail endpoint
        $this->warn('═══ Detail Endpoint: /api/users/applications/detail ═══');
        $debug = $service->debugGetApplicationsDetail();
        $this->displayDebugInfo($debug);

        $this->line('');
        $this->info('✓ Debug info collected');
    }

    /**
     * Display comprehensive debug output.
     */
    private function showAllDebug(UserApplicationsService $service): void
    {
        $this->info('🔍 Fetching comprehensive debug output...');
        $this->line('');

        $allDebug = $service->debugAll();

        // Session info
        $this->warn('═══ Session Information ═══');
        $session = $allDebug['session'];
        $this->line('Session ID:    ' . $session['id']);
        $this->line('Has IAM Token: ' . ($session['has_iam_token'] ? '✓ Yes' : '✗ No'));
        $this->line('Has IAM Sub:   ' . ($session['has_iam_sub'] ? '✓ Yes' : '✗ No'));
        $this->line('IAM Sub:       ' . ($session['iam_sub'] ?? '-'));
        $this->line('IAM App:       ' . ($session['iam_app'] ?? '-'));

        $this->line('');

        // Endpoints
        $this->warn('═══ Configured Endpoints ═══');
        $endpoints = $allDebug['endpoints'];
        $this->line('Base URL:      ' . $endpoints['base_url']);
        $this->line('Applications:  ' . $endpoints['applications']);
        $this->line('Detail:        ' . $endpoints['applications_detail']);

        $this->line('');

        // Basic endpoint
        $this->warn('═══ Basic Endpoint Response ═══');
        $this->displayDebugInfo($allDebug['basic_endpoint']);

        $this->line('');

        // Detail endpoint
        $this->warn('═══ Detail Endpoint Response ═══');
        $this->displayDebugInfo($allDebug['detail_endpoint']);

        $this->line('');
        $this->info('Execution Time: ' . $allDebug['execution_time_ms'] . 'ms');
        $this->info('✓ Full debug output completed');
    }

    /**
     * Helper: Display formatted debug info.
     */
    private function displayDebugInfo(array $debug): void
    {
        // Check for errors first
        if (isset($debug['error'])) {
            $this->error('Error: ' . $debug['error']);
            if (isset($debug['exception_class'])) {
                $this->line('Class: ' . $debug['exception_class']);
            }
            return;
        }

        if (isset($debug['status'])) {
            $statusColor = isset($debug['successful']) && $debug['successful'] ? 'info' : 'error';
            $this->line("<{$statusColor}>Status: {$debug['status']}</>");
        }

        if (isset($debug['url'])) {
            $this->line('URL:             ' . $debug['url']);
        }

        if (isset($debug['headers'])) {
            if ($debug['headers']['content-type'] ?? false) {
                $this->line('Content-Type:    ' . $debug['headers']['content-type']);
            }
        }

        if (isset($debug['body_size'])) {
            $this->line('Response Size:   ' . $debug['body_size'] . ' bytes');
        }

        if (isset($debug['body'])) {
            $this->line('Response:');
            $this->line(json_encode($debug['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}
