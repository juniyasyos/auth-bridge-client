# Accessing IAM User Applications - Quick Start

This guide shows how to access the `/api/users/applications` and `/api/users/applications/detail` routes from your IAM client application.

## 🚀 Quick Start (30 seconds)

### Step 1: Inject the Service
```php
use Juniyasyos\IamClient\Services\UserApplicationsService;

class YourController extends Controller
{
    public function __construct(private UserApplicationsService $appsService) {}
}
```

### Step 2: Fetch Applications
```php
$apps = $this->appsService->getApplications();
```

### Step 3: Check for Errors
```php
if (isset($apps['error'])) {
    // Handle error
    return redirect('/login');
}
```

### Step 4: Use the Data
```php
foreach ($apps['applications'] ?? [] as $app) {
    echo $app['name'];
    echo $app['app_url'];
    echo $app['logo_url'] ?? '';
}
```

---

## 📱 In Your Blade Template

```blade
@php
    $service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
    $apps = $service->getApplications();
@endphp

<div class="app-grid">
    @forelse($apps['applications'] ?? [] as $app)
        <div class="app-card">
            @if($app['logo_url'])
                <img src="{{ $app['logo_url'] }}" alt="{{ $app['name'] }}">
            @endif
            
            <h3>{{ $app['name'] }}</h3>
            <p>{{ $app['description'] }}</p>
            
            <div class="badges">
                <span class="status">{{ $app['status'] }}</span>
                <span class="roles">{{ $app['roles_count'] }} roles</span>
            </div>
            
            @if($app['app_url'])
                <a href="{{ $app['app_url'] }}" class="btn" target="_blank">
                    Open Application →
                </a>
            @endif
        </div>
    @empty
        <p>No accessible applications</p>
    @endforelse
</div>
```

---

## 🔍 Available Methods

### `getApplications()`
Fetch applications with basic metadata.
```php
$appsService->getApplications();
// Returns: app_key, name, logo_url, app_url, roles, etc.
```

### `getApplicationsDetail()`
Fetch applications with complete metadata.
```php
$appsService->getApplicationsDetail();
// Returns: All of above + timestamps, access profiles, etc.
```

### `getApplicationByKey(string $appKey)`
Find a specific application.
```php
$app = $appsService->getApplicationByKey('siimut');
if ($app) {
    echo $app['name'];
}
```

### Debug Methods
```php
$appsService->debugGetApplications();      // Debug basic endpoint
$appsService->debugGetApplicationsDetail(); // Debug detail endpoint
$appsService->debugAll();                  // Complete debug info
```

---

## 📊 Response Structure

### What You Get Back

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
            'description' => '...',
            'enabled' => true,
            'status' => 'active',
            'logo_url' => 'https://example.com/logo.png',
            'app_url' => 'http://127.0.0.1:8088',
            'redirect_uris' => ['http://127.0.0.1:8088'],
            'callback_url' => 'http://127.0.0.1:8088/callback',
            'backchannel_url' => null,
            'roles_count' => 1,
            'has_logo' => true,
            'has_primary_url' => true,
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
    'timestamp' => '2026-04-01T12:00:00Z'
]
```

---

## ⚠️ Error Handling

### Possible Errors

```php
$result = $appsService->getApplications();

if (isset($result['error'])) {
    switch ($result['error']) {
        case 'iam_token_missing':
            // User not logged in via IAM
            return redirect('/login');
        
        case 'iam_server_error':
            // IAM server error (e.g., 401, 500)
            Log::error('IAM error', $result);
            return response('Service unavailable', 503);
        
        case 'iam_request_error':
            // Network error
            Log::error('Connection error', $result);
            return response('Connection error', 500);
    }
}
```

---

## 🛠️ Debugging

### Using Artisan Command

```bash
# Show applications list
php artisan iam:user-applications

# Show detailed information
php artisan iam:user-applications --detail

# Show debug info
php artisan iam:user-applications --debug

# Full debug output
php artisan iam:user-applications --all
```

### Using Tinker

```bash
php artisan tinker
```

```php
$service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
$apps = $service->getApplications();
$apps['applications'][0]; // First app
$service->debugAll(); // See all debug info
```

### Check Logs

```bash
tail -f storage/logs/laravel.log | grep "UserApplicationsService"
```

---

## 💾 Caching Applications

For better performance, cache the results:

```php
<span class="text-xs text-gray-500">
$apps = Cache::remember(
    'user_' . auth()->id() . '_apps',
    3600,  // 1 hour
    fn() => $this->appsService->getApplications()
);
</span>
```

Clear cache when user roles change:

```php
Cache::forget('user_' . auth()->id() . '_apps');
```

---

## 📖 Full Documentation

- **QUICK-REFERENCE.md** - Complete API reference
- **USAGE-EXAMPLES.md** - 10 detailed code examples
- **TESTING-GUIDE.md** - How to test
- **SERVICE-IMPLEMENTATION.md** - Implementation details

---

## ✅ Requirements

- User must be authenticated via IAM SSO
- IAM access token must be in session
- IAM server must be accessible
- Routes must be configured in `/routes/api.php`

---

## 🎯 Common Patterns

### Build App Launcher
```php
$apps = $service->getApplications();
return view('launcher', compact('apps'));
```

### Check User Access
```php
$app = $service->getApplicationByKey('siimut');
$hasAccess = $app !== null;
```

### Display App with Logo
```blade
@if($app['has_logo'])
    <img src="{{ $app['logo_url'] }}" alt="{{ $app['name'] }}">
@else
    <div class="initials">{{ substr($app['name'], 0, 1) }}</div>
@endif
```

### Redirect to App
```php
$app = $service->getApplicationByKey('siimut');
if ($app && $app['app_url']) {
    return redirect($app['app_url']);
}
```

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| "IAM token missing" | User needs to login via IAM SSO |
| "Server returned 401" | Token expired, user needs to re-login |
| "No applications" | Check IAM user has roles/profiles assigned |
| Slow response | Use caching or check IAM server |
| See errors | Run `php artisan iam:user-applications --all` |

---

## 📞 Need Help?

1. Check **QUICK-REFERENCE.md** for API reference
2. See **USAGE-EXAMPLES.md** for code examples
3. Run **`php artisan iam:user-applications --debug`** to debug
4. Check logs: **`tail -f storage/logs/laravel.log`**
5. Use **`php artisan tinker`** to test directly

