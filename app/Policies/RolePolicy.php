<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\StaffPermission;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::RolesView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission(StaffPermission::RolesView);
    }
}
