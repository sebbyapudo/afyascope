<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\StaffPermission;

class AuditLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::AuditView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasPermission(StaffPermission::AuditView);
    }
}
