<?php

namespace DigitalCloud\PermissionTool\Resources\Traits;

use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Http\Requests\NovaRequest;

trait AuthorizedQueries
{
    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {

        if (! $request->isResourceIndexRequest()) {
            return $query;
        }

        $resource = $request->resource();

        // Do not apply on excluded resources
        if (in_array($resource, config('permission.permissions.exclude_resources', []))) {
            return $query;
        }

        // Allow view on all records
        $permission = sprintf('viewAny-%s', $resource);

        if (Gate::check($permission)) {
            return $query;
        }

        // Is User Model.
        // Requires check against id field
        if (get_class($query->getModel()) == config('permission.models.user')) {
            return $query->where('id', request()->user()->id);
        }

        // Record belongsTo or is AssignedTo User or being Watched By
        if ($resource::$model === $query->getModel()::class && method_exists($query->getModel(), 'assignees') && method_exists($query->getModel(), 'watchers')) {
            return $query->where(function ($q) {
                $q->whereHas('assignees', function ($q) {
                    $q->where('users.id', request()->user()->id);
                })->orWhere(config('permission.column_names.user_id'), request()->user()->id)
                ->orWhereHas('watchers', function ($q) {
                    $q->where('users.id', request()->user()->id);
                });
            });
        } else if ($resource::$model === $query->getModel()::class && method_exists($query->getModel(), 'assignees') && !method_exists($query->getModel(), 'watchers')) {
            // Record belongsTo or is AssignedTo User
            return $query->where(function ($q) {
                $q->whereHas('assignees', function ($q) {
                    $q->where('users.id', request()->user()->id);
                })->orWhere(config('permission.column_names.user_id'), request()->user()->id);
            });
        }

        // Record belongsTo User
        if (method_exists($query->getModel(), 'owner')) {
            return $query->where(config('permission.column_names.user_id'), request()->user()->id);
        }

        return $query;
    }
}
