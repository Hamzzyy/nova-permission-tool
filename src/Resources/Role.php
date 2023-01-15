<?php

namespace DigitalCloud\PermissionTool\Resources;

use Laravel\Nova\Nova;
use Laravel\Nova\Resource;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\BelongsToMany;
use Spatie\Permission\PermissionRegistrar;
use Laravel\Nova\Http\Requests\NovaRequest;
use DigitalCloud\PermissionTool\PermissionTool;
use DigitalCloud\PermissionTool\Services\InitializePermissions;

class Role extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \DigitalCloud\PermissionTool\Models\Role::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name',
    ];

    public static $displayInNavigation = true;

    public static function getModel()
    {
        return app(PermissionRegistrar::class)->getRoleClass();
    }

    public static function label()
    {
        return __('PermissionTool::resources.Roles');
    }

    public static function singularLabel()
    {
        return __('PermissionTool::resources.Role');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\NovaRequest\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $userResource = Nova::resourceForModel(getModelForGuard($this->guard_name));

        $fields = [
            ID::make()->sortable(),

            Text::make(__('PermissionTool::roles.name'), 'name')
                ->rules(['required', 'string', 'max:255'])
                ->creationRules('unique:' . config('permission.table_names.roles'))
                ->updateRules('unique:' . config('permission.table_names.roles') . ',name,{{resourceId}}'),

            \DigitalCloud\PermissionTool\Fields\Permission::make(__('PermissionTool::resources.Permissions'), 'permissions')->onlyOnForms()->stacked()->size('w-full'),

            Text::make('users count')->withMeta(['value' => $this->users()->count()])->exceptOnForms(),
            Text::make('permissions count')->withMeta(['value' => $this->permissions()->count()])->exceptOnForms(),
            DateTime::make(__('PermissionTool::roles.created_at'), 'created_at')->exceptOnForms(),
            DateTime::make(__('PermissionTool::roles.updated_at'), 'updated_at')->exceptOnForms(),

            BelongsToMany::make(__('PermissionTool::resources.Permissions'), 'permissions', Permission::class),
            MorphToMany::make($userResource::label(), 'users', $userResource),
        ];

        return $fields;
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\NovaRequest\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\NovaRequest\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\NovaRequest\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }

    //    public function authorizedToDelete(Request $request)
    //    {
    //        return false;
    //    }
    //
    //    public function authorizedToUpdate(Request $request)
    //    {
    //        return ($this->id == 1)? false : true;
    //    }
}
