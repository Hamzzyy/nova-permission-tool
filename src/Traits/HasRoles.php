<?php

namespace DigitalCloud\PermissionTool\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasRoles
{
    use HasPermissions;

    protected $queuedRoles = [];

    private $roleClass;

    public static function bootHasRoles()
    {
        static::created(function ($model) {
            $model->assignRole($model->queuedRoles);
            $model->queuedRoles = [];
        });

        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->roles()->detach();
        });
    }

    public function setRolesAttribute($roles)
    {
        if (! $this->exists) {
            $this->queuedRoles = $roles;

            return;
        }
        $this->syncRoles($roles);
    }

    public function getRoleClass()
    {
        if (! isset($this->roleClass)) {
            $this->roleClass = app(PermissionRegistrar::class)->getRoleClass();
        }

        return $this->roleClass;
    }

    /**
     * A model may have multiple roles.
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            PermissionRegistrar::$pivotRole
        );

        if (! PermissionRegistrar::$teams) {
            return $relation;
        }

        return $relation->wherePivot(PermissionRegistrar::$teamsKey, getPermissionsTeamId())
            ->where(function ($q) {
                $teamField = config('permission.table_names.roles') . '.' . PermissionRegistrar::$teamsKey;
                $q->whereNull($teamField)->orWhereIn($teamField, getPermissionsTeamId());
            });
    }

    /**
     * Scope the model query to certain roles only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @param string $guard
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRole(Builder $query, $roles, $guard): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (! is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(function ($role) {
            if ($role instanceof Role) {
                return $role;
            }

            $method = is_numeric($role) ? 'findById' : 'findByName';

            return $this->getRoleClass()->{$method}($role, $this->getDefaultGuardName());
        }, Arr::wrap($roles));

        return $query->whereHas('roles', function (Builder $subQuery) use ($roles) {
            $roleClass = $this->getRoleClass();
            $key = (new $roleClass())->getKeyName();
            $subQuery->whereIn(config('permission.table_names.roles') . ".{$key}", \array_column($roles, $key));
        });
    }

    /**
     * Assign the given role to the model.
     *
     * @param array|string|\Spatie\Permission\Contracts\Role ...$roles
     *
     * @return $this
     */
    public function assignRole(...$roles)
    {
        $roles = collect($roles)
            ->flatten()
            ->reduce(function ($array, $role) {
                if (empty($role)) {
                    return $array;
                }

                $role = $this->getStoredRole($role);

                if (! $role instanceof Role) {
                    return $array;
                }

                $this->ensureModelSharesGuard($role);

                $array[$role->getKey()] = PermissionRegistrar::$teams && ! is_a($this, Permission::class) ?
                    [PermissionRegistrar::$teamsKey => getPermissionsTeamId()] : [];

                return $array;
            }, []);

        $model = $this->getModel();

        if ($model->exists) {
            $this->roles()->sync($roles, false);
            $model->load('roles');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($roles, $model) {
                    if ($model->getKey() != $object->getKey()) {
                        return;
                    }
                    $model->roles()->sync($roles, false);
                    $model->load('roles');
                }
            );
        }

        if (is_a($this, get_class($this->getPermissionClass()))) {
            $this->forgetCachedPermissions();
        }

        return $this;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param string|\Spatie\Permission\Contracts\Role $role
     */
    public function removeRole($role)
    {
        $this->roles()->detach($this->getStoredRole($role));

        $this->load('roles');

        if (is_a($this, get_class($this->getPermissionClass()))) {
            $this->forgetCachedPermissions();
        }

        return $this;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param array|\Spatie\Permission\Contracts\Role|string ...$roles
     *
     * @return $this
     */
    public function syncRoles(...$roles)
    {
        $this->roles()->detach();

        return $this->assignRole($roles);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @param string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasRole($roles, string $guard = null): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $guard
                ? $this->roles->where('guard_name', $guard)->contains('name', $roles)
                : $this->roles->contains('name', $roles);
        }

        if (is_int($roles)) {
            $roleClass = $this->getRoleClass();
            $key = (new $roleClass())->getKeyName();

            return $guard
                ? $this->roles->where('guard_name', $guard)->contains($key, $roles)
                : $this->roles->contains($key, $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains($roles->getKeyName(), $roles->getKey());
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }

            return false;
        }

        return $roles->intersect($guard ? $this->roles->where('guard_name', $guard) : $this->roles)->isNotEmpty();
    }

    /**
     * Determine if the model has any of the given role(s).
     *
     * @param string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @param string|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAllRoles($roles, string $guard = null): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $guard
                ? $this->roles->where('guard_name', $guard)->contains('name', $roles)
                : $this->roles->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains($roles->getKeyName(), $roles->getKey());
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $roles->intersect(
            $guard
                ? $this->roles->where('guard_name', $guard)->pluck('name')
                : $this->getRoleNames()
        ) == $roles;
    }

    /**
     * Determine if the model has exactly all of the given role(s).
     *
     * @param  string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection  $roles
     * @param  string|null  $guard
     * @return bool
     */
    public function hasExactRoles($roles, string $guard = null): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            $roles = [$roles];
        }

        if ($roles instanceof Role) {
            $roles = [$roles->name];
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $this->roles->count() == $roles->count() && $this->hasAllRoles($roles, $guard);
    }

    /**
     * Return all permissions directly coupled to the model.
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }

    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    protected function getStoredRole($role): Role
    {
        $roleClass = $this->getRoleClass();

        if (is_numeric($role)) {
            return $roleClass->findById($role, $this->getDefaultGuardName());
        }

        if (is_string($role)) {
            return $roleClass->findByName($role, $this->getDefaultGuardName());
        }

        return $role;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (! in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }
}
