<?php

namespace DigitalCloud\PermissionTool\Services;

use Laravel\Nova\Nova;
use Laravel\Nova\Http\Requests\NovaRequest;
use DigitalCloud\PermissionTool\PermissionTool;

class InitializePermissions
{
    protected $rolePermissions = [];

    public function getRolePermissions()
    {
        return $this->rolePermissions;
    }

    public function handle(NovaRequest $request)
    {
        $this->setupPermission($request);
    }

    protected function setupPermission(NovaRequest $request)
    {
        $this->setupResourcePermissions($request);
        $this->setupToolPermissions();
        $this->setupDashboardPermisisons();
        $this->setupCustomPermissions();
        $this->syncPermissions();
    }

    protected function setupResourcePermissions(NovaRequest $request)
    {
        $resourcePermissions = config('permission.permissions.resource');

        foreach (Nova::$resources as $resource) {
            if ($resource == 'Laravel\Nova\Actions\ActionResource') {
                continue;
            }

            if (in_array($resource, config('permission.permissions.exclude_resources'))) {
                continue;
            }
            // $resourceName = strtolower(substr(strrchr($resource, "\\"), 1));
            // $resourceName = $resource;

            foreach ($resourcePermissions as $permission) {
                $value = "{$permission}-{$resource}";
                $this->rolePermissions[] = $value;
            }

            // add resource actions
            $resourceInstance = new $resource($resource::$model);
            $this->setupActionPermissions($request, $resourceInstance, $resource);
            $this->setupFieldPermissions($request, $resourceInstance, $resource);
        }
    }

    protected function setupActionPermissions($request, $resourceInstance, $resource)
    {
        foreach ($resourceInstance->actions($request) as $action) {
            if ($action->name) {
                $name = $action->name . "-{$resource}";
            } else {
                $name = $action::class . "-{$resource}";
            }
            $this->rolePermissions[] = $name;
        }
    }

    protected function setupFieldPermissions($request, $resourceInstance, $resource)
    {
        if (! in_array($resource, ['DigitalCloud\PermissionTool\Resources\Role', 'DigitalCloud\PermissionTool\Resources\Permission'])) {
            foreach ($resourceInstance->fields($request) as $field) {
                if (in_array($field::class, ['Eminiarts\Tabs\Tabs', 'Laravel\Nova\Panel'])) {
                    $field->data = collect($field->data)->each(function ($nestedField) use ($resource) {
                        $this->getVisibleFieldPermission($nestedField, $resource);
                        $this->getWritableOFieldPermission($nestedField, $resource);
                        $this->getIdentifiableFieldPermission($nestedField, $resource);
                    });

                    continue;
                }

                if (in_array($field->attribute, config('permission.permissions.excluded_fields'))) {
                    continue;
                }
                $this->getVisibleFieldPermission($field, $resource);
                $this->getWritableOFieldPermission($field, $resource);
                $this->getIdentifiableFieldPermission($field, $resource);
            }
        }
    }

    protected function getVisibleFieldPermission($field, $resource)
    {
        if ($field->attribute) {
            $name = $field->attribute . ' (visible)' . "-{$resource}";

            if ($field->attribute === 'ComputedField') {
                $name = $field->name . ' (visible)' . "-{$resource}";
            }
            $this->rolePermissions[] = $name;
        }
    }

    protected function getWritableOFieldPermission($field, $resource)
    {
        if ($field->attribute) {
            $name = $field->attribute . ' (writable)' . "-{$resource}";

            if ($field->attribute === 'ComputedField') {
                $name = $field->name . ' (writable)' . "-{$resource}";
            }
            $this->rolePermissions[] = $name;
        }
    }

    public function getIdentifiableFieldPermission($field, $resource) 
    {
        if ($field->attribute && $field->attribute === 'notes') {
            $name = $field->attribute . ' (identifiable)' . "-{$resource}";
            $this->rolePermissions[] = $name;
        }

    }

    protected function setupToolPermissions()
    {
        $tools = collect(Nova::$tools)->filter(function ($tool) {
            return $tool->menu(request()) && ! in_array($tool::class, [
                'Laravel\Nova\Tools\Dashboard',
                'Laravel\Nova\Tools\ResourceManager',
                "DigitalCloud\PermissionTool\PermissionTool",
            ]);
        })->toArray();

        foreach ($tools as $tool) {
            $this->rolePermissions[] = PermissionTool::getToolPermission($tool);
        }
    }

    public function setupDashboardPermisisons()
    {
        $dashboards = collect(Nova::$dashboards)->filter(function ($dashboard) {
            return ! in_array($dashboard::class, [
                'App\Nova\Dashboards\Main',
            ]);
        })->toArray();

        foreach ($dashboards as $dashboard) {
            $this->rolePermissions[] = PermissionTool::getDashboardPermission($dashboard);
        }
    }

    protected function setupCustomPermissions()
    {
        $permissions = config('permission.permissions.custom_permissions', []);

        foreach ($permissions as $key => $permission) {
            if (is_array($permission)) {
                foreach ($permission as $p) {
                    $this->rolePermissions[] = sprintf('%s-%s', $p, $key);
                }
            } else {
                $this->rolePermissions[] = sprintf('%s-CustomPermission', $permission);
            }
        }
    }

    protected function syncPermissions()
    {
        foreach (collect($this->rolePermissions)->unique()->toArray() as $resourcePermission) {
            \DigitalCloud\PermissionTool\Models\Permission::firstOrCreate(
                ['name' => $resourcePermission],
                ['guard_name' => 'web']
            );
        }
        \DigitalCloud\PermissionTool\Models\Permission::whereNotIn('name', $this->rolePermissions)->delete();
    }
}
