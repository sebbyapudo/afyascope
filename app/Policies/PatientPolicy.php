<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\StaffPermission;

class PatientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::PatientsView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Patient $patient): bool
    {
        return $user->hasPermission(StaffPermission::PatientsView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::PatientsCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Patient $patient): bool
    {
        return $user->hasPermission(StaffPermission::PatientsUpdate);
    }
}
