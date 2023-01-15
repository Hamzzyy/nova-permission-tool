<?php

namespace DigitalCloud\PermissionTool;

use Gate;
use Laravel\Nova\Nova;
use Laravel\Nova\Events\ServingNova;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use DigitalCloud\PermissionTool\Http\Middleware\Authorize;

class ToolServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'PermissionTool');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'PermissionTool');

        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/PermissionTool'),
        ], 'PermissionTool-lang');
        $this->app->booted(function () {
            $this->routes();
        });

        $this->publishes([
            __DIR__ . '/config/permission.php' => config_path('permission.php'),
        ]);

        $this->publishes([
            __DIR__. '/../database/migrations' => database_path('migrations')
        ], 'permission-tool-migrations');

        // Super admin all permissions
        Gate::before(function ($user, $ability) {
            if (in_array($user->email, config('permission.permissions.admin_emails'))) {
                return true;
            }
        });

        Nova::serving(function (ServingNova $event) {
        });
    }

    /**
     * Register the tool's routes.
     *
     * @return void
     */
    protected function routes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Nova::router(['nova', Authorize::class], 'permission-tool')
            ->group(__DIR__ . '/../routes/inertia.php');

        Route::middleware(['nova', Authorize::class])
            ->prefix('nova-vendor/PermissionTool')
            ->group(__DIR__ . '/../routes/api.php');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
