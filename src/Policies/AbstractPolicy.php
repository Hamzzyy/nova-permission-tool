<?php

declare(strict_types = 1);

namespace DigitalCloud\PermissionTool\Policies;

use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\HandlesAuthorization;

class AbstractPolicy
{
    use HandlesAuthorization;
    public $resource;

    public function resourceClass()
    {
        return $this->resource;
    }

    public function check($permission, $record = null)
    {
        $resource = $this->resourceClass();
        $permission = sprintf('%s-%s', $permission, $resource);

        if (! $record) {
            return Gate::check($permission);
        }

        $userIdCol = config('permission.column_names.user_id');
        $hasUserAttribute = array_key_exists($userIdCol, $record->getAttributes());

        if ($record && Gate::check($permission)) {
            if ($hasUserAttribute && $record->$userIdCol == request()->user()->id) {
                return true;
            }

            if (method_exists($record, 'assignees') && $record->assignees->firstWhere('id', request()->user()->id)) {
                return true;
            }

            if (method_exists($record, 'watchers') && $record->watchers->firstWhere('id', request()->user()->id)) {
                return true;
            }

            if ($resource::$model == config('permission.models.user') && request()->user()->id === $record->id) {
                return true;
            }

            return false;
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
}
