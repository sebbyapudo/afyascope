<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\StaffPermission;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::AppointmentsView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->hasPermission(StaffPermission::AppointmentsView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::AppointmentsCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->hasPermission(StaffPermission::AppointmentsUpdate);
    }
}
