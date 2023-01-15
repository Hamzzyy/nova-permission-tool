<?php

namespace DigitalCloud\PermissionTool\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends SpatieRole
{
    use HasFactory;
    public function setPermissionsAttribute($value)
    {
        $this->syncPermissions($value);
    }
}
