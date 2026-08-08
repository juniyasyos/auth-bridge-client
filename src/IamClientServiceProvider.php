<?php

namespace Juniyasyos\IamClient;

use Illuminate\Support\ServiceProvider;

class IamClientServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package config with application config
        $configPath = __DIR__ . '/../config/iam.php';

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'iam');
        }

        // Warn if the configured identifier_field is not present in user_fields mapping.
        // This helps catch misconfigurations like identifier_field=nip without mapping 'nip' => 'nip'.
        $this->app->booted(function () {
            $identifier = config('iam.identifier_field', 'email');
            $fields = array_keys(config('iam.user_fields', []));

            if ($identifier && !in_array($identifier, $fields, true)) {
                \Illuminate\Support\Facades\Log::warning('IAM config mismatch: identifier_field [' . $identifier . '] is not mapped in iam.user_fields; provisioning may fail.');
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config/migrations/views (always available)
        $this->publishes([
            __DIR__ . '/../config/iam.php' => config_path('iam.php'),
        ], 'iam-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'iam-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/iam-client'),
        ], 'iam-views');

        // Register middleware aliases — compatible with Laravel 10–13.
        // router->aliasMiddleware() from a package ServiceProvider still works in all versions.
        $this->registerMiddlewareAliases();

        // API routes should remain available even when the web SSO flows are disabled.
        $this->loadRoutesFrom(__DIR__ . '/../routes/iam-api.php');

        // If package disabled via config, skip the web routes and optional runtime registrations.
        if (!config('iam.enabled', true)) {
            return;
        }

        // Load routes, migrations and views when enabled
        $this->loadRoutesFrom(__DIR__ . '/../routes/iam-client.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'iam-client');

        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::component('iam-app-switcher', \Juniyasyos\IamClient\Http\Livewire\IamAppSwitcher::class);
        }

        // Optionally auto-attach middlewares to the `web` group when configured.
        $this->registerMiddlewareGroups();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Juniyasyos\IamClient\Console\Commands\CheckIamSyncUsers::class,
                \Juniyasyos\IamClient\Console\Commands\UserApplicationsCommand::class,
            ]);
        }
    }

    /**
     * Register middleware aliases.
     *
     * Uses router->aliasMiddleware() which is compatible with Laravel 10–13 when
     * called from within a package ServiceProvider. Applications using Laravel 11+
     * can additionally reference these aliases from bootstrap/app.php via
     * $middleware->alias([...]) if they prefer to centralise configuration there.
     */
    protected function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('iam.auth', \Juniyasyos\IamClient\Http\Middleware\EnsureAuthenticated::class);
        $router->aliasMiddleware('iam.backchannel.verify', \Juniyasyos\IamClient\Http\Middleware\VerifyIamBackchannelSignature::class);
        $router->aliasMiddleware('iam.verify', \Juniyasyos\IamClient\Http\Middleware\VerifyIamToken::class);
        $router->aliasMiddleware('iam.timeout', \Juniyasyos\IamClient\Http\Middleware\EnforceSessionTimeout::class);
    }

    /**
     * Optionally push middlewares into the `web` middleware group.
     *
     * router->pushMiddlewareToGroup() is compatible with Laravel 10–13.
     * On Laravel 11+, package-registered middleware groups are merged into
     * the application's middleware stack automatically.
     */
    protected function registerMiddlewareGroups(): void
    {
        $router = $this->app['router'];

        // Optionally auto-attach the verify middleware to the `web` group when configured
        if (config('iam.attach_verify_middleware', true)) {
            $router->pushMiddlewareToGroup('web', \Juniyasyos\IamClient\Http\Middleware\VerifyIamToken::class);
        }

        // Optionally auto-attach session timeout enforcement middleware to the `web` group
        if (config('iam.attach_enforce_timeout_middleware', true)) {
            $router->pushMiddlewareToGroup('web', \Juniyasyos\IamClient\Http\Middleware\EnforceSessionTimeout::class);
        }
    }
}
