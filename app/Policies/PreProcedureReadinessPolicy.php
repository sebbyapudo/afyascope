<?php

namespace App\Policies;

use App\Models\PreProcedureReadiness;
use App\Models\User;
use App\PreProcedureReadinessStatus;
use App\StaffPermission;

class PreProcedureReadinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::NursingManage);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PreProcedureReadiness $preProcedureReadiness): bool
    {
        return $user->hasPermission(StaffPermission::NursingManage);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::NursingManage);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PreProcedureReadiness $preProcedureReadiness): bool
    {
        return $user->hasPermission(StaffPermission::NursingManage)
            && $preProcedureReadiness->nurse_user_id === $user->id
            && $preProcedureReadiness->status === PreProcedureReadinessStatus::InPreparation;
    }

    public function complete(User $user, PreProcedureReadiness $preProcedureReadiness): bool
    {
        return $this->update($user, $preProcedureReadiness);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PreProcedureReadiness $preProcedureReadiness): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PreProcedureReadiness $preProcedureReadiness): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PreProcedureReadiness $preProcedureReadiness): bool
    {
        return false;
    }
}
