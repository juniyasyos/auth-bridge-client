<?php

/**
 * UserApplicationsService - Usage Examples
 * 
 * This file demonstrates how to use the UserApplicationsService
 * in various scenarios within the client application.
 */

// ============================================================================
// EXAMPLE 1: Basic Usage in a Controller
// ============================================================================

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Juniyasyos\IamClient\Services\UserApplicationsService;

class DashboardController extends Controller
{
    public function __construct(
        private UserApplicationsService $appsService
    ) {}

    /**
     * Display user's accessible applications on dashboard.
     */
    public function index(Request $request)
    {
        // Fetch user's applications from IAM server
        $appsData = $this->appsService->getApplications();

        // Handle errors
        if (isset($appsData['error'])) {
            return view('dashboard', [
                'error' => $appsData['message'] ?? 'Failed to load applications',
                'applications' => [],
            ]);
        }

        return view('dashboard', [
            'applications' => $appsData['applications'] ?? [],
            'totalApps' => $appsData['total_accessible_apps'] ?? 0,
        ]);
    }

    /**
     * Display detailed application information page.
     */
    public function applicationsDetail(Request $request)
    {
        $detailData = $this->appsService->getApplicationsDetail();

        if (isset($detailData['error'])) {
            return back()->with('error', $detailData['message'] ?? 'Failed to load application details');
        }

        return view('applications.detail', [
            'applications' => $detailData['applications'] ?? [],
            'userProfiles' => $detailData['user_profiles'] ?? [],
        ]);
    }
}

// ============================================================================
// EXAMPLE 2: Using in Blade Templates
// ============================================================================

@section('content')
<div class="applications-grid">
    @php
        $appsService = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
        $apps = $appsService->getApplications();
    @endphp

    @if(isset($apps['error']))
        <div class="alert alert-warning">
            {{ $apps['message'] ?? 'Unable to load applications' }}
        </div>
    @elseif(!empty($apps['applications']))
        @foreach($apps['applications'] as $app)
            <div class="app-card">
                @if($app['logo_url'])
                    <img src="{{ $app['logo_url'] }}" alt="{{ $app['name'] }}" class="app-logo">
                @else
                    <div class="app-logo-placeholder">
                        {{ substr($app['name'], 0, 1) }}
                    </div>
                @endif

                <h3>{{ $app['name'] }}</h3>
                <p>{{ $app['description'] ?? 'No description' }}</p>

                <div class="app-meta">
                    <span class="badge {{ $app['enabled'] ? 'badge-success' : 'badge-secondary' }}">
                        {{ $app['status'] ?? 'inactive' }}
                    </span>
                    <span class="badge badge-info">{{ $app['roles_count'] ?? 0 }} roles</span>
                </div>

                @if($app['app_url'])
                    <a href="{{ $app['app_url'] }}" class="btn btn-primary" target="_blank">
                        Open Application
                    </a>
                @endif
            </div>
        @endforeach
    @else
        <div class="alert alert-info">No accessible applications</div>
    @endif
</div>
@endsection

// ============================================================================
// EXAMPLE 3: Using in a Service Class
// ============================================================================

namespace App\Services;

use Juniyasyos\IamClient\Services\UserApplicationsService;

class ApplicationAuthorizationService
{
    public function __construct(
        private UserApplicationsService $appsService
    ) {}

    /**
     * Check if user has access to a specific application.
     */
    public function userHasAccessToApp(string $appKey): bool
    {
        $app = $this->appsService->getApplicationByKey($appKey);
        return $app !== null;
    }

    /**
     * Get all accessible app keys for the current user.
     */
    public function getAccessibleAppKeys(): array
    {
        $appsData = $this->appsService->getApplications();
        
        if (isset($appsData['error'])) {
            return [];
        }

        return $appsData['accessible_apps'] ?? [];
    }

    /**
     * Get user's access profile information.
     */
    public function getUserAccessProfiles(): array
    {
        $detailData = $this->appsService->getApplicationsDetail();
        
        if (isset($detailData['error'])) {
            return [];
        }

        return $detailData['user_profiles'] ?? [];
    }
}

// ============================================================================
// EXAMPLE 4: Debugging in Development
// ============================================================================

// In web routes (development only)
Route::middleware(['auth', 'debug'])->group(function () {
    Route::get('/debug/iam/applications', function () {
        $service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
        
        return [
            'basic' => $service->getApplications(),
            'detailed' => $service->getApplicationsDetail(),
            'debug' => $service->debugAll(),
        ];
    });
});

// Or using Laravel Tinker:
/*
>>> $service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
>>> $service->getApplications();
>>> $service->getApplicationsDetail();
>>> $service->debugGetApplications();
>>> $service->debugAll();
*/

// ============================================================================
// EXAMPLE 5: Using the Artisan Command
// ============================================================================

/*
# Show basic applications
php artisan iam:user-applications

# Show detailed information
php artisan iam:user-applications --detail

# Show debug information
php artisan iam:user-applications --debug

# Show complete debug output
php artisan iam:user-applications --all

Output example:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📦 SIIMUT - Sistem Informasi Manajemen Indikator Mutu Terpadu

  App Key:        siimut
  Status:         active
  Primary URL:    http://127.0.0.1:8088
  Has Logo:       ✗ No
  Roles Count:    1
  Roles:
    - Super Admin (super_admin)
*/

// ============================================================================
// EXAMPLE 6: Error Handling
// ============================================================================

namespace App\Http\Controllers;

use Juniyasyos\IamClient\Services\UserApplicationsService;

class AppsController extends Controller
{
    public function list(UserApplicationsService $appsService)
    {
        $result = $appsService->getApplications();

        // Check for errors
        if (isset($result['error'])) {
            // Handle different error scenarios
            switch ($result['error']) {
                case 'iam_token_missing':
                    return redirect('/login')->with('error', 'IAM session expired. Please login again.');
                
                case 'iam_server_error':
                    \Log::error('IAM server error', $result);
                    return back()->with('error', 'Unable to reach IAM server. Try again later.');
                
                case 'iam_request_error':
                    \Log::error('IAM request failed', $result);
                    return back()->with('error', 'Network error. ' . $result['message']);
                
                default:
                    return back()->with('error', 'Unknown error occurred.');
            }
        }

        return view('apps.list', [
            'applications' => $result['applications'] ?? [],
        ]);
    }
}

// ============================================================================
// EXAMPLE 7: Building a Dynamic Menu
// ============================================================================

namespace App\View\Components;

use Illuminate\View\Component;
use Juniyasyos\IamClient\Services\UserApplicationsService;

class ApplicationMenu extends Component
{
    public array $applications = [];
    public bool $hasError = false;
    public string $errorMessage = '';

    public function __construct(
        private UserApplicationsService $appsService
    ) {
        $this->load();
    }

    private function load(): void
    {
        $result = $this->appsService->getApplications();

        if (isset($result['error'])) {
            $this->hasError = true;
            $this->errorMessage = $result['message'] ?? 'Failed to load applications';
            return;
        }

        // Filter by status
        $this->applications = collect($result['applications'] ?? [])
            ->filter(fn($app) => $app['enabled'] ?? true)
            ->sortBy('name')
            ->toArray();
    }

    public function render()
    {
        return view('components.application-menu');
    }
}

// In template:
<x-application-menu />

// ============================================================================
// EXAMPLE 8: Middleware for Per-App Authorization
// ============================================================================

namespace App\Http\Middleware;

use Closure;
use Juniyasyos\IamClient\Services\UserApplicationsService;

class CheckAppAccess
{
    public function __construct(
        private UserApplicationsService $appsService
    ) {}

    public function handle($request, Closure $next, string $appKey)
    {
        $app = $this->appsService->getApplicationByKey($appKey);

        if (!$app) {
            return response('Unauthorized', 403);
        }

        // Store in request for later use
        $request->mergeIfMissing(['accessible_app' => $app]);

        return $next($request);
    }
}

// Usage in routes:
Route::middleware(['auth', 'check.app.access:siimut'])
    ->get('/siimut', 'SiimutController@index');

// ============================================================================
// EXAMPLE 9: API Response Transformation
// ============================================================================

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this['id'],
            'key' => $this['app_key'],
            'name' => $this['name'],
            'url' => $this['app_url'],
            'logo' => $this['logo_url'],
            'isActive' => $this['enabled'] ?? true,
            'roles' => collect($this['roles'] ?? [])
                ->pluck('name')
                ->toArray(),
            'canAccess' => true,
        ];
    }
}

// ============================================================================
// EXAMPLE 10: Caching Applications (for performance)
// ============================================================================

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Juniyasyos\IamClient\Services\UserApplicationsService;

class CachedApplicationService
{
    public const CACHE_DURATION = 3600; // 1 hour

    public function __construct(
        private UserApplicationsService $baseService
    ) {}

    public function getApplications(): array
    {
        $cacheKey = 'user_' . auth()->id() . '_applications';

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () {
            return $this->baseService->getApplications();
        });
    }

    public function refreshApplications(): array
    {
        $cacheKey = 'user_' . auth()->id() . '_applications';
        Cache::forget($cacheKey);
        
        return $this->getApplications();
    }

    public function getApplicationsDetail(): array
    {
        $cacheKey = 'user_' . auth()->id() . '_applications_detail';

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () {
            return $this->baseService->getApplicationsDetail();
        });
    }
}
