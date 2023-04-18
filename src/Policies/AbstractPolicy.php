<?php

declare(strict_types = 1);

namespace DigitalCloud\PermissionTool\Policies;

use App\Nova\Lead;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class AbstractPolicy
{
    use HandlesAuthorization;

    public $resource;

    public function resourceClass()
    {
        return $this->resource;
    }

    public function check($resourcePermission, $record = null)
    {
        $resource = $this->resourceClass();
        $permission = sprintf('%s-%s', $resourcePermission, $resource);

        if (! $record) {
            return Gate::check($permission);
        }

        // @todo added to make leads page reload fast for sales team. Remove when solved
        if ($this->resource == Lead::class) {
            if (in_array($resourcePermission, ['view', 'update'])) {
                if (auth()->user()->roles()->where('name', 'Sales')->exists()) {
                    // sales agent
                    return true;
                } else {
                    // Not a sales agent
                    return false;
                }
            } else {
                // delete lead permission
                return false;
            }
        }
        $request = app(NovaRequest::class);

        $userIdCol = config('permission.column_names.user_id');
        $hasUserAttribute = array_key_exists($userIdCol, $record->getAttributes());

        if ($record && Gate::check($permission)) {
            // If index view, then indexQuery() has already narrowed down selections so pass
            if (app(NovaRequest::class)->isResourceIndexRequest() && $resourcePermission == 'view') {
                return true;
            }

            // Is owner
            if ($hasUserAttribute && $record->$userIdCol == $request->user()->id) {
                return true;
            }

            // Record belongs to External Portal User
            if (method_exists($record, 'user') && $record->user?->id == $request->user()->id) {
                return true;
            }

            if (method_exists($record, 'assignees') && $record->assignees->firstWhere('id', $request->user()->id)) {
                return true;
            }

            if (method_exists($record, 'watchers') && $record->watchers->firstWhere('id', $request->user()->id)) {
                return true;
            }

            if ($resource::$model == config('permission.models.user') && $request->user()->id === $record->id) {
                return true;
            }

            // Record is accessible through a Related Resource
            if (method_exists($this->resource, 'tempAuthorizeViaRelationshipQuery')) {
                // $authorizedViaRelationship = $this->resource::authorizeViaRelationshipQuery((app(NovaRequest::class)), $record);
                $authorizedViaRelationship = $this->resource::tempAuthorizeViaRelationshipQuery((app(NovaRequest::class)), $record);

                if ($authorizedViaRelationship) {
                    return true;
                }
            }
        }

        return false;
    }

    public function viewAny($user): bool
    {
        if ($this->check('view') || $this->check('viewAny') || $this->check('create')) {
            return true;
        }

        return false;
    }

    public function view($user, $record): bool
    {
        if ($this->check('viewAny') || $this->check('view', $record)) {
            return true;
        }

        return false;
    }

    public function create(): bool
    {
        if ($this->check('create')) {
            return true;
        }

        return false;
    }

    public function update($user, $record): bool
    {
        if ($this->check('updateAny') || $this->check('update', $record)) {
            return true;
        }

        return false;
    }

    public function delete($user, $record): bool
    {
        if ($this->check('deleteAny') || $this->check('delete', $record)) {
            return true;
        }

        return false;
    }

    public function restore(): bool
    {
        if ($this->check('restore')) {
            return true;
        }

        return false;
    }

    public function forceDelete(): bool
    {
        if ($this->check('forceDelete')) {
            return true;
        }

        return false;
    }

    public function runAction()
    {
        return app(NovaRequest::class)->action()->authorizedToSee(request());
    }
}
