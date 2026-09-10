<?php

namespace App\Policies;

use App\Models\RecoveryEscalation;
use App\Models\User;
use App\RecoveryEscalationStatus;
use App\StaffPermission;
use App\StaffRole;

class RecoveryEscalationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveDoctorWithRecoveryAccess($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RecoveryEscalation $recoveryEscalation): bool
    {
        return $this->isActiveDoctorWithRecoveryAccess($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RecoveryEscalation $recoveryEscalation): bool
    {
        return $this->resolve($user, $recoveryEscalation);
    }

    public function resolve(User $user, RecoveryEscalation $recoveryEscalation): bool
    {
        return $this->isActiveDoctorWithRecoveryAccess($user)
            && $recoveryEscalation->status === RecoveryEscalationStatus::Open;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RecoveryEscalation $recoveryEscalation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RecoveryEscalation $recoveryEscalation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RecoveryEscalation $recoveryEscalation): bool
    {
        return false;
    }

    private function isActiveDoctorWithRecoveryAccess(User $user): bool
    {
        return $user->is_active
            && $user->role?->slug === StaffRole::Doctor->value
            && $user->hasPermission(StaffPermission::RecoveryView);
    }
}
