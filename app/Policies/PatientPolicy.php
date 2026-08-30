<?php

namespace App\Policies;

use App\Models\User;
use App\StaffPermission;

class PatientPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::PatientsCreate);
    }
}
