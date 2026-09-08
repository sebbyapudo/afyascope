<?php

namespace App\Policies;

use App\Models\ProcedureRecord;
use App\Models\User;
use App\ProcedureRecordStatus;
use App\StaffPermission;

class ProcedureRecordPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::ProceduresManage);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProcedureRecord $procedureRecord): bool
    {
        return $user->hasPermission(StaffPermission::ProceduresView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::ProceduresManage);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProcedureRecord $procedureRecord): bool
    {
        return $user->hasPermission(StaffPermission::ProceduresManage)
            && $procedureRecord->doctor_user_id === $user->id
            && $procedureRecord->status === ProcedureRecordStatus::InProgress;
    }

    public function complete(User $user, ProcedureRecord $procedureRecord): bool
    {
        return $this->update($user, $procedureRecord);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProcedureRecord $procedureRecord): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProcedureRecord $procedureRecord): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProcedureRecord $procedureRecord): bool
    {
        return false;
    }
}
