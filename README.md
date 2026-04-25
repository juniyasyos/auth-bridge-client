# Auth Bridge Client

Laravel package for IAM Single Sign-On (SSO), JWT verification, and JIT user provisioning.

## Why use this package?

This package is designed for client applications that need to:

- authenticate users via IAM
- provision users automatically during login
- synchronize roles and application access
- verify tokens on every request
- support optional unit kerja sync

## Highlights

- ✅ Minimal setup for a Laravel client
- ✅ IIS-compatible JWT verification
- ✅ JIT user provisioning from the IAM token
- ✅ Optional role sync with Spatie Permission
- ✅ Built-in IAM sync endpoints for user/role data
- ✅ Optional Livewire app switcher for current IAM applications

## Requirements

- PHP `^8.1`
- Laravel `^10.0 | ^11.0 | ^12.0`
- `firebase/php-jwt`
- `spatie/laravel-permission` (optional)

## Quick setup

### 1. Install the package

```bash
composer require juniyasyos/auth-bridge-client
```

### 2. Publish config

```bash
php artisan vendor:publish --tag=iam-config
```

### 3. Run migrations

```bash
php artisan migrate
```

### 4. Set environment variables

```env
IAM_ENABLED=true
IAM_APP_KEY=your-app-key
IAM_JWT_SECRET=your-jwt-secret
IAM_BASE_URL=https://iam.example.com
IAM_VERIFY_ENDPOINT=https://iam.example.com/api/verify
IAM_PRESERVE_SESSION_ID=true
IAM_SYNC_ROLES=true
```

### 5. Configure your User model

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    protected $fillable = [
        'iam_id',
        'name',
        'email',
        'status',
    ];
}
```

> Use `status` instead of `active`. The package expects `status` values like `active`, `inactive`, or `suspended`.

### 6. Configure routes

The package already registers the main IAM routes automatically when enabled.

If you need protected pages, use middleware:

```php
Route::middleware(['iam.auth:web'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});
```

### 7. Add a login link

Use the built-in login route:

```blade
<a href="{{ route('iam.sso.login') }}">Login via IAM</a>
```

## Configuration overview

Open `config/iam.php` and adjust the following sections.

### SSO settings

- `iam.app_key` — IAM application key
- `iam.jwt_secret` — shared JWT secret for validating tokens
- `iam.base_url` — base URL of the IAM server
- `iam.login_route` / `iam.callback_route` — local login/callback URLs
- `iam.default_redirect_after_login` — where to send users after login
- `iam.guard` — auth guard used by default

### User sync settings

- `iam.user_fields` — map database columns to JWT claims
- `iam.identifier_field` — primary field used to identify users
- `iam.sync_users` — exposes `/api/iam/sync-users`
- `iam.sync_roles` — enable role sync during provisioning

### Token verification

- `iam.verify_each_request` — validate token on every request
- `iam.attach_verify_middleware` — automatically push `iam.verify` into the `web` middleware group

### Unit Kerja sync (optional)

- `iam.unit_kerja_field` — JWT claim name for unit/org data
- `iam.require_unit_kerja` — reject login if unit/org is missing
- `iam.sync_unit_kerja` — sync `unitKerjas()` relation on the user model
- `iam.unit_kerja_model` — model for unit/org records

## Routes registered by the package

The package exposes these routes when enabled:

- `iam.sso.login` — redirect user to IAM login
- `iam.sso.callback` — handle callback and provisioning
- `iam.logout` — logout and clear IAM session
- `iam.sync-users` — IAM pulls client user data
- `iam.sync-roles` — IAM pulls client role data
- `iam.push-roles` — IAM pushes authoritative role updates
- `iam.push-users` — IAM pushes user updates to client
- `iam.health` — health check endpoint

## Middleware aliases

- `iam.auth` — ensures the user is authenticated
- `iam.verify` — verifies token on each request
- `iam.backchannel.verify` — verifies IAM back-channel payload signatures

## Usage steps for client apps

1. Install the package and publish config.
2. Run migrations.
3. Set `IAM_ENABLED=true`, `IAM_APP_KEY`, `IAM_JWT_SECRET`, and `IAM_BASE_URL`.
4. Confirm your `User` model has `iam_id`, `email`, `name`, and `status`.
5. Protect routes with `iam.auth:web`.
6. Add a login link using `route('iam.sso.login')`.
7. If needed, publish views for customization:

```bash
php artisan vendor:publish --tag=iam-views
```

## Example token payload

IAM should send a JWT payload like:

```json
{
  "type": "access",
  "app_key": "your-app-key",
  "sub": 123,
  "name": "John Doe",
  "email": "john@example.com",
  "nip": "123456",
  "roles": [{"slug": "admin"}],
  "unit_kerja": ["Finance", "IT"],
  "exp": 1234567890
}
```

## Custom field mapping

Update `config/iam.php`:

```php
'user_fields' => [
    'iam_id' => 'sub',
    'name' => 'name',
    'email' => 'email',
    'nip' => 'nip',
    'nik' => 'nik',
],
'identifier_field' => 'iam_id',
```

## Events

A successful login dispatches the `IamAuthenticated` event. Use it for auditing or custom actions.

```php
use Juniyasyos\IamClient\Events\IamAuthenticated;

Event::listen(IamAuthenticated::class, function ($event) {
    // $event->user
    // $event->payload
    // $event->guard
});
```

## License

MIT

'guards' => [
    'web' => [
        'guard' => 'web',
        'redirect_route' => '/',
        'login_route_name' => 'login',
        'logout_redirect_route' => 'home',
    ],
],
```

To add a new guard, register your own route and set `defaults('guard', 'your_guard')` or pass the guard parameter to the controller.

## Event Hooks

A successful login dispatches the `IamAuthenticated` event. Listen to this event for auditing, downstream provisioning, or custom logging.

```php
use Juniyasyos\IamClient\Events\IamAuthenticated;

Event::listen(IamAuthenticated::class, function ($event) {
    // $event->user, $event->payload, $event->guard
});
```

## License

MIT
