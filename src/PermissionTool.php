<?php

namespace DigitalCloud\PermissionTool;

use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Events\ServingNova;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Http\Requests\NovaRequest;
use DigitalCloud\PermissionTool\Resources\Role;
use DigitalCloud\PermissionTool\Resources\Permission;
use DigitalCloud\PermissionTool\Policies\AbstractPolicy;
use DigitalCloud\PermissionTool\Services\InitializePermissions;

class PermissionTool extends Tool
{
    public $roleResource = Role::class;
    public $permissionResource = Permission::class;

    /**
     * Perform any tasks that need to happen when the tool is booted.
     *
     * @return void
     */
    public function boot()
    {
        Nova::resources([
            $this->roleResource,
            $this->permissionResource,
        ]);

        $lock = Cache::lock('permissionsInit', 86400);

        if ($lock->get()) {
            (new InitializePermissions())->handle((new NovaRequest()));
        }

        Nova::script('PermissionTool', __DIR__ . '/../dist/js/tool.js');
        Nova::style('PermissionTool', __DIR__ . '/../dist/css/tool.css');

        $this->registerPolicies();
    }

    /**
     * Build the view that renders the navigation links for the tool.
     *
     * @return \Illuminate\View\View
     */
    public function menu(Request $request)
    {
        // return MenuSection::make('Permission Tool', [
        //     MenuItem::resource(Role::class),
        //     MenuItem::resource(Permission::class),
        // ])

        //     ->icon('server');
    }

    public function roleResource(string $roleResource)
    {
        $this->roleResource = $roleResource;

        return $this;
    }

    public function permissionResource(string $permissionResource)
    {
        $this->permissionResource = $permissionResource;

        return $this;
    }

    public function registerPolicies()
    {
        $abstractPolicy = AbstractPolicy::class;
        $resources = Nova::$resources;

        foreach ($resources as $resource) {
            if ($resource == 'Laravel\Nova\Actions\ActionResource') {
                continue;
            }
            $anonymousPolicy = eval("return (new class extends {$abstractPolicy} {
                public \$resource = '{$resource}';
            });");
            Gate::policy($resource::$model, $anonymousPolicy::class);
        }
    }

    public static function registerFieldPermissions($resourceInstance, $fieldsList)
    {
        $fieldsWithPermissions = [];
        $resource = $resourceInstance::class;

        foreach ($fieldsList as $field) {
            if (in_array($field::class, ['Eminiarts\Tabs\Tabs', 'Laravel\Nova\Panel'])) {
                $field->data = collect($field->data)->each(function ($nestedField) use ($resource) {
                    return self::checkFieldPermission($nestedField, $resource);
                })->toArray();
                $fieldsWithPermissions[] = $field;

                continue;
            }

            $fieldsWithPermissions[] = self::checkFieldPermission($field, $resource);
        }

        return $fieldsWithPermissions;
    }

    public static function checkFieldPermission($field, $resource)
    {
        if (in_array(Auth::user()->email, config('permission.permissions.admin_emails'))) {
            return $field;
        }

        if (! Auth::user()->roles->count()) {
            return $field;
        }

        if (in_array($field->attribute, config('permission.permissions.excluded_fields'))) {
            return $field;
        }

        if ($field->attribute) {
            $field->readonly(function () use ($field, $resource) {
                if ($field->attribute === 'ComputedField') {
                    return !Gate::check($field->name . ' (writable)' . "-{$resource}");
                }

                return !Gate::check($field->attribute . ' (writable)' . "-{$resource}");
            });
            $field->canSee(function () use ($field, $resource) {
                if ($field->attribute === 'ComputedField') {
                    return Gate::check($field->name . ' (visible)' . "-{$resource}");
                }

                return Gate::check($field->attribute . ' (visible)' . "-{$resource}");
            });
        }

        return $field;
    }

    public static function registerToolPermissions()
    {
        $tools = collect(Nova::$tools)->filter(function ($tool) {
            return $tool->menu(request()) && ! in_array($tool::class, [
                // Laravel Nova Offical Resources
                'Laravel\Nova\Tools\Dashboard',
                'Laravel\Nova\Tools\ResourceManager',
                // -----END----
                "DigitalCloud\PermissionTool\PermissionTool",
            ]);
        });
        $tools->each(function ($tool) {
            $tool->canSee(function () use ($tool) {
                return Gate::check(static::getToolPermission($tool));
            });
        });
    }

    public static function registerDashboardPermissions()
    {
        $dashboards = collect(Nova::$dashboards)->filter(function ($dashboard) {
            return ! in_array($dashboard::class, [
                'App\Nova\Dashboards\Main',
            ]);
        });
        $dashboards->each(function ($dashboard) {
            $dashboard->canSee(function () use ($dashboard) {
                return Gate::check(static::getDashboardPermission($dashboard));
            });
        });
    }

    /**
     * @param String $tool
     * @return String
     */
    public static function getToolPermission($tool)
    {
        return sprintf('%s-Laravel\Nova\Tool', $tool::class);
    }

    public static function getDashboardPermission($dashboard)
    {
        if ($dashboard->name) {
            return sprintf('%s-Laravel\Nova\Dashboard', $dashboard->name);
        }
        return sprintf('%s-Laravel\Nova\Dashboard', $dashboard::class);
    }

    public static function register()
    {
        Nova::serving(function (ServingNova $event) {
            self::registerToolPermissions();
            self::registerDashboardPermissions();
        });
    }
}
