<?php

namespace App\Policies;

use App\Models\RecoveryEpisode;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffPermission;

class RecoveryEpisodePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::RecoveryView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RecoveryEpisode $recoveryEpisode): bool
    {
        return $user->hasPermission(StaffPermission::RecoveryView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::RecoveryManage);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RecoveryEpisode $recoveryEpisode): bool
    {
        return $user->hasPermission(StaffPermission::RecoveryManage)
            && $recoveryEpisode->nurse_user_id === $user->id
            && $recoveryEpisode->status === RecoveryEpisodeStatus::InProgress;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RecoveryEpisode $recoveryEpisode): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RecoveryEpisode $recoveryEpisode): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RecoveryEpisode $recoveryEpisode): bool
    {
        return false;
    }
}
