<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitCheckIn;
use App\StaffPermission;

class VisitCheckInPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::CheckInView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VisitCheckIn $visitCheckIn): bool
    {
        return $user->hasPermission(StaffPermission::CheckInView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::CheckInCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VisitCheckIn $visitCheckIn): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VisitCheckIn $visitCheckIn): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VisitCheckIn $visitCheckIn): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VisitCheckIn $visitCheckIn): bool
    {
        return false;
    }
}
