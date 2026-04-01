# UserApplicationsService - Quick Reference

## Overview

The `UserApplicationsService` provides a simple way to access user's accessible applications from the IAM server within the client plugin.

**Location**: `src/Services/UserApplicationsService.php`  
**Namespace**: `Juniyasyos\IamClient\Services`

## Installation

The service is automatically available after installing the IAM client plugin via service provider registration.

## Basic Usage

### Dependency Injection (Recommended)

```php
use Juniyasyos\IamClient\Services\UserApplicationsService;

class DashboardController extends Controller
{
    public function __construct(
        private UserApplicationsService $appsService
    ) {}

    public function show()
    {
        $apps = $this->appsService->getApplications();
        return view('dashboard', ['apps' => $apps['applications']]);
    }
}
```

### Service Container

```php
$service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
$apps = $service->getApplications();
```

### Facade (if registered in provider)

```php
// If you add a facade to IamClientServiceProvider
$apps = \IamApps::getApplications();
```

## Methods

### `getApplications(): array`

Fetches user's accessible applications with metadata.

**Returns**:
```php
[
    'source' => 'iam-server',
    'sub' => '1',
    'user_id' => 1,
    'total_accessible_apps' => 2,
    'applications' => [
        [
            'id' => 1,
            'app_key' => 'siimut',
            'name' => 'SIIMUT - Sistem Informasi...',
            'description' => 'App description',
            'enabled' => true,
            'status' => 'active',
            'logo_url' => 'https://...',
            'app_url' => 'http://127.0.0.1:8088',
            'redirect_uris' => ['http://127.0.0.1:8088'],
            'callback_url' => 'http://127.0.0.1:8088/callback',
            'backchannel_url' => null,
            'roles_count' => 1,
            'has_logo' => false,
            'has_primary_url' => true,
            'urls' => [
                'primary' => 'http://127.0.0.1:8088',
                'all_redirects' => ['http://127.0.0.1:8088'],
                'callback' => 'http://127.0.0.1:8088/callback',
                'backchannel' => null,
            ],
            'roles' => [
                [
                    'id' => 1,
                    'slug' => 'super_admin',
                    'name' => 'Super Admin',
                    'is_system' => false,
                    'description' => 'Full system access'
                ]
            ]
        ]
    ],
    'accessible_apps' => ['siimut', 'other-app'],
    'timestamp' => '2026-04-01T...'
]
```

**Example**:
```php
$result = $appsService->getApplications();

if (isset($result['error'])) {
    // Handle error
    echo $result['message'];
} else {
    // Process applications
    foreach ($result['applications'] as $app) {
        echo $app['name'];
        echo $app['app_url'];
    }
}
```

---

### `getApplicationsDetail(): array`

Fetches comprehensive application information with full metadata.

**Returns Additional Fields**:
```php
[
    'applications' => [
        [
            // All fields from getApplications() plus:
            'metadata' => [
                'logo' => [
                    'url' => 'https://...',
                    'available' => false,
                ],
                'urls' => [
                    'primary' => 'http://127.0.0.1:8088',
                    'all_redirects' => ['http://127.0.0.1:8088'],
                    'callback' => 'http://127.0.0.1:8088/callback',
                    'backchannel' => null,
                ],
                'created_at' => '2026-04-01T...',
                'updated_at' => '2026-04-01T...',
            ],
            'access_profiles_using_this_app' => [
                [
                    'id' => 1,
                    'name' => 'Super Admin',
                    'slug' => 'super-admin'
                ]
            ]
        ]
    ],
    'user_profiles' => [
        // List of user's access profiles
    ]
]
```

**Example**:
```php
$detail = $appsService->getApplicationsDetail();

foreach ($detail['applications'] as $app) {
    // Display logo if available
    if ($app['metadata']['logo']['available']) {
        echo "<img src='{$app['metadata']['logo']['url']}' />";
    }
    
    // Display access profiles
    foreach ($app['access_profiles_using_this_app'] as $profile) {
        echo $profile['name'];
    }
}
```

---

### `getApplicationByKey(?string $appKey): ?array`

Find a specific application by app_key.

**Parameters**:
- `$appKey`: Application key (e.g., 'siimut')

**Returns**: Application array or `null` if not found

**Example**:
```php
$app = $appsService->getApplicationByKey('siimut');

if ($app) {
    echo $app['name'];
    echo $app['app_url'];
} else {
    echo 'Application not found';
}
```

---

## Debugging Methods

### `debugGetApplications(): array`

Get raw response from `/users/applications` endpoint.

**Example**:
```php
$debug = $appsService->debugGetApplications();
echo json_encode($debug, JSON_PRETTY_PRINT);
```

**Output**:
```json
{
  "name": "BasicApplications",
  "url": "https://iam.server/api/users/applications",
  "status": 200,
  "successful": true,
  "headers": {...},
  "body_size": 2048,
  "body": {...}
}
```

---

### `debugGetApplicationsDetail(): array`

Get raw response from `/users/applications/detail` endpoint.

**Example**:
```php
$debug = $appsService->debugGetApplicationsDetail();
// Same structure as above
```

---

### `debugAll(): array`

Get comprehensive debugging information for both endpoints.

**Example**:
```php
$allDebug = $appsService->debugAll();
dd($allDebug);
```

**Includes**:
- Session information (ID, token status)
- Configured endpoints
- Responses from both endpoints
- Execution time

---

## Error Handling

### Error Codes

```php
$result = $appsService->getApplications();

if (isset($result['error'])) {
    switch ($result['error']) {
        case 'iam_token_missing':
            // User not authenticated via IAM
            redirect('/login');
            break;
        
        case 'iam_server_error':
            // IAM server returned error status
            Log::error($result['message']);
            show_error_page();
            break;
        
        case 'iam_request_error':
            // Network/connection error
            Log::error($result['message']);
            show_error_page();
            break;
    }
}
```

### Complete Error Handling Example

```php
$apps = $appsService->getApplications();

return view('applications', [
    'applications' => $apps['applications'] ?? [],
    'hasError' => isset($apps['error']),
    'errorMessage' => $apps['message'] ?? null,
    'errorCode' => $apps['error'] ?? null,
]);
```

---

## Artisan Command

Access the service via Artisan command:

```bash
# Show basic applications
php artisan iam:user-applications

# Show detailed information
php artisan iam:user-applications --detail

# Show debug information
php artisan iam:user-applications --debug

# Show complete debug output
php artisan iam:user-applications --all
```

---

## Laravel Tinker Usage

```bash
php artisan tinker
```

```php
$service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);

// Get applications
$apps = $service->getApplications();

// Get applications detail
$detail = $service->getApplicationsDetail();

// Find specific app
$app = $service->getApplicationByKey('siimut');

// Debug
$debug = $service->debugAll();

// Exit tinker
exit
```

---

## Common Patterns

### Check User Access to App

```php
$app = $appsService->getApplicationByKey('siimut');
$hasAccess = $app !== null;
```

### Display App Logo

```php
@if($app['has_logo'])
    <img src="{{ $app['logo_url'] }}" alt="{{ $app['name'] }}">
@else
    <div class="placeholder">{{ substr($app['name'], 0, 1) }}</div>
@endif
```

### Get All App URLs

```php
$allUrls = [
    'primary' => $app['app_url'],
    'redirects' => $app['redirect_uris'] ?? [],
    'callback' => $app['callback_url'],
    'backchannel' => $app['backchannel_url'],
];
```

### Count Roles per App

```php
$totalRoles = collect($apps['applications'] ?? [])
    ->sum('roles_count');
```

### Filter Apps by Status

```php
$activeApps = collect($apps['applications'] ?? [])
    ->filter(fn($app) => $app['enabled'] ?? true)
    ->toArray();
```

---

## Configuration

The service uses existing IAM client configuration:

```php
// config/iam.php
[
    'base_url' => env('IAM_BASE_URL', 'http://localhost:8000'),
    'user_applications_endpoint' => env('IAM_USER_APPLICATIONS_ENDPOINT'),
    // ...
]
```

If not configured, service automatically constructs endpoint:
```
{IAM_BASE_URL}/api/users/applications
{IAM_BASE_URL}/api/users/applications/detail
```

---

## Logging

All requests are logged to (check logs):
```bash
tail -f storage/logs/laravel.log
```

Contains:
- Successful requests with response info
- Failed requests with status/body
- Exceptions with full stack trace
- Session ID for debugging

---

## Performance Tips

1. **Cache Requests** (for 1 hour):
```php
Cache::remember('apps_' . auth()->id(), 3600, fn() => 
    $service->getApplications()
);
```

2. **Load Only Needed Data**:
   - Use `getApplications()` for list views
   - Use `getApplicationsDetail()` only for detail pages

3. **Handle Errors Gracefully**:
```php
$apps = $appsService->getApplications();
if (isset($apps['error'])) {
    return $defaultApps;
}
```

---

## Troubleshooting

### "IAM access token not found"
- User not authenticated via IAM SSO
- Session expired
- Solution: Redirect to login

### "IAM server returned error"
- Check IAM server status
- Verify endpoint configuration
- Check logs for details

### Slow Responses
- IAM server may be slow
- Use caching
- Check network connectivity

### No Applications Returned
- User has no accessible apps
- Check IAM user profiles
- Verify role assignments

---

## See Also

- [Usage Examples](./USAGE-EXAMPLES.md) - Detailed code examples
- [IAM Documentation](../laravel-iam/docs/README.md) - IAM server docs
- [API Routes](../laravel-iam/routes/api.php) - API endpoint definitions
