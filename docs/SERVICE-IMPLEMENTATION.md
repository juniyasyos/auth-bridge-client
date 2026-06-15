# UserApplicationsService - Complete Implementation

## 📋 Summary

I've created a complete implementation for accessing IAM server application routes from the Laravel IAM client plugin. The service provides methods to fetch user's accessible applications with detailed metadata (logos, URLs, roles, etc.) along with comprehensive debugging capabilities.

---

## 🎯 What Was Created

### 1. **UserApplicationsService** 
**File**: `src/Services/UserApplicationsService.php`

A robust service class that:
- Fetches applications from `/api/users/applications` endpoint
- Fetches detailed applications from `/api/users/applications/detail` endpoint
- Provides filtering by app_key
- Includes comprehensive error handling
- Offers debugging methods for development
- Logs all requests and errors

**Key Methods**:
```php
// Basic usage
$apps = $service->getApplications();
$detailedApps = $service->getApplicationsDetail();
$app = $service->getApplicationByKey('siimut');

// Debugging
$service->debugGetApplications();
$service->debugGetApplicationsDetail();
$service->debugAll();
```

---

### 2. **UserApplicationsCommand**
**File**: `src/Console/Commands/UserApplicationsCommand.php`

An Artisan command for testing and debugging:

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

Features:
- Pretty-formatted table output
- Shows logos, URLs, roles, and metadata
- Helpful for debugging token/connection issues
- Registered automatically in service provider

---

### 3. **Quick Reference Guide**
**File**: `QUICK-REFERENCE.md`

Complete documentation including:
- Installation instructions
- Basic usage patterns
- All available methods with examples
- Error handling guide
- Common patterns
- Performance tips
- Troubleshooting

---

### 4. **Usage Examples**
**File**: `USAGE-EXAMPLES.md`

10 detailed examples showing:
1. Using in a Controller
2. Using in Blade Templates
3. Using in a Service Class
4. Debugging in Development
5. Using the Artisan Command
6. Error Handling Patterns
7. Building Dynamic Menus
8. Middleware for Per-App Authorization
9. API Response Transformation
10. Caching Applications

---

### 5. **Testing Guide**
**File**: `TESTING-GUIDE.md`

Test examples covering:
- Unit testing with mocked HTTP
- Integration testing
- Manual testing via Artisan
- Manual testing via Tinker
- Real server testing

---

### 6. **Service Provider Updates**
**File**: `src/IamClientServiceProvider.php`

Added `UserApplicationsCommand` to the commands array for automatic registration.

---

## 🚀 Getting Started

### For Client Application Developers

#### 1. **In a Controller**
```php
use Juniyasyos\IamClient\Services\UserApplicationsService;

class DashboardController extends Controller
{
    public function __construct(private UserApplicationsService $appsService) {}

    public function index()
    {
        $apps = $this->appsService->getApplications();
        return view('dashboard', ['apps' => $apps['applications'] ?? []]);
    }
}
```

#### 2. **In Blade Templates**
```blade
@php
    $service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
    $apps = $service->getApplications();
@endphp

@foreach($apps['applications'] ?? [] as $app)
    <div class="app-card">
        @if($app['logo_url'])
            <img src="{{ $app['logo_url'] }}" alt="{{ $app['name'] }}">
        @endif
        <h3>{{ $app['name'] }}</h3>
        <a href="{{ $app['app_url'] }}" target="_blank">Open</a>
    </div>
@endforeach
```

#### 3. **Via Service Container**
```php
$service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
$apps = $service->getApplications();
```

#### 4. **Via Artisan Command**
```bash
php artisan iam:user-applications --detail
```

---

## 📊 What Data You Get

### Basic Endpoint (`getApplications()`)
```json
{
  "source": "iam-server",
  "sub": "1",
  "user_id": 1,
  "total_accessible_apps": 2,
  "applications": [
    {
      "id": 1,
      "app_key": "siimut",
      "name": "SIIMUT - Sistem Informasi...",
      "description": "Application description",
      "enabled": true,
      "status": "active",
      "logo_url": "https://example.com/logo.png",
      "app_url": "http://127.0.0.1:8088",
      "redirect_uris": ["http://127.0.0.1:8088"],
      "callback_url": "http://127.0.0.1:8088/callback",
      "backchannel_url": null,
      "roles_count": 1,
      "has_logo": true,
      "has_primary_url": true,
      "urls": {
        "primary": "http://127.0.0.1:8088",
        "all_redirects": ["http://127.0.0.1:8088"],
        "callback": "http://127.0.0.1:8088/callback",
        "backchannel": null
      },
      "roles": [
        {
          "id": 1,
          "slug": "super_admin",
          "name": "Super Admin",
          "is_system": false,
          "description": "Full system access"
        }
      ]
    }
  ],
  "accessible_apps": ["siimut", "other-app"],
  "timestamp": "2026-04-01T12:00:00Z"
}
```

### Detail Endpoint (`getApplicationsDetail()`)
Includes all the above PLUS:
```json
{
  "metadata": {
    "logo": {
      "url": "https://example.com/logo.png",
      "available": true
    },
    "urls": { /* same as above */ },
    "created_at": "2026-04-01T00:00:00Z",
    "updated_at": "2026-04-01T12:00:00Z"
  },
  "access_profiles_using_this_app": [
    {
      "id": 1,
      "name": "Super Admin",
      "slug": "super-admin"
    }
  ]
}
```

---

## 🔍 Debugging Features

### Method 1: Artisan Command
```bash
php artisan iam:user-applications --all
```
Shows:
- Session info
- Configured endpoints
- Both endpoint responses
- Execution time

### Method 2: Via Service
```php
$debug = $service->debugAll();
dd($debug);
```

### Method 3: Via Tinker
```bash
php artisan tinker
>>> $service = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class);
>>> $service->debugAll();
```

### Method 4: Check Logs
```bash
tail -f storage/logs/laravel.log | grep UserApplicationsService
```

---

## ⚠️ Error Handling

The service returns errors in a consistent format:

```php
[
    'source' => 'iam-error',
    'error' => 'iam_token_missing',          // Error code
    'message' => 'IAM access token...',      // Human readable
    'endpoint' => '/users/applications',
    'session_id' => '...',
]
```

**Error Codes**:
- `iam_token_missing` - User not authenticated via IAM
- `iam_server_error` - IAM server returned error (check status)
- `iam_request_error` - Network/connection error

---

## 📁 Files Created/Modified

### Created Files:
1. ✅ `src/Services/UserApplicationsService.php` (280+ lines)
2. ✅ `src/Console/Commands/UserApplicationsCommand.php` (300+ lines)
3. ✅ `QUICK-REFERENCE.md` (Comprehensive API reference)
4. ✅ `USAGE-EXAMPLES.md` (10 complete examples)
5. ✅ `TESTING-GUIDE.md` (Test examples)

### Modified Files:
1. ✅ `src/IamClientServiceProvider.php` (Added command registration)

---

## ✨ Key Features

- ✅ **Zero-dependency**: Uses only Laravel's built-in Http client
- ✅ **Error handling**: Graceful fallbacks and detailed error messages
- ✅ **Logging**: All requests logged for debugging
- ✅ **Type hints**: Full PHP type hints for IDE support
- ✅ **Session aware**: Automatically uses IAM token from session
- ✅ **Flexible**: Works in controllers, services, middleware, console
- ✅ **Debuggable**: Multiple debugging options for development
- ✅ **Documented**: Extensive documentation with examples
- ✅ **Tested**: Test examples provided

---

## 🔄 How It Works

```
1. Client App User Logs In via IAM SSO
   └─> IAM token stored in session('iam.access_token')

2. Client App Requests Applications
   └─> UserApplicationsService::getApplications()
   └─> Fetches token from session
   └─> Makes HTTP request to IAM server: /api/users/applications
   └─> Returns formatted response

3. Client App Uses Application Data
   └─> Display logos, URLs, roles
   └─> Check access, build menus
   └─> Link to applications
```

---

## 📚 Documentation Files

1. **QUICK-REFERENCE.md** - Fast lookup for methods
2. **USAGE-EXAMPLES.md** - Real-world code examples
3. **TESTING-GUIDE.md** - How to test the service
4. **This file** - Implementation summary

---

## 🎓 Next Steps

1. **Review** the QUICK-REFERENCE.md for method documentation
2. **Check** USAGE-EXAMPLES.md for your specific use case
3. **Run** `php artisan iam:user-applications` to test with real data
4. **Integrate** into your client application
5. **Debug** using `debugAll()` if issues arise

---

## 💡 Common Use Cases

### 1. Display App Launcher
```php
$apps = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class)
    ->getApplications();

return view('launcher', ['apps' => $apps['applications'] ?? []]);
```

### 2. Check App Access
```php
$hasAccess = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class)
    ->getApplicationByKey('siimut') !== null;
```

### 3. Show App Details
```php
$detail = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class)
    ->getApplicationsDetail();
```

### 4. Debug Issues
```php
$debug = app(\Juniyasyos\IamClient\Services\UserApplicationsService::class)
    ->debugAll();
dd($debug);
```

---

## 🐛 Troubleshooting

### "IAM access token not found"
→ User not authenticated via IAM, redirect to login

### "IAM server returned 401"
→ Token expired, session cleared, user needs to re-login

### "No applications returned"
→ Check IAM user has roles/profiles assigned

### Slow responses
→ Use caching or check IAM server status

### Check logs:
```bash
tail -f storage/logs/laravel.log | grep "UserApplicationsService"
```

---

## 📞 Support Files

For detailed information, see:
- `QUICK-REFERENCE.md` - API reference
- `USAGE-EXAMPLES.md` - Code examples
- `TESTING-GUIDE.md` - Testing patterns
- Service comments - Inline documentation
- Artisan command - `php artisan iam:user-applications --all`

