<?php

namespace App\Policies;

use App\Models\FinancialClearance;
use App\Models\User;
use App\StaffPermission;

class FinancialClearancePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::ClearanceView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FinancialClearance $financialClearance): bool
    {
        return $user->hasPermission(StaffPermission::ClearanceView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::ClearanceCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FinancialClearance $financialClearance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FinancialClearance $financialClearance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FinancialClearance $financialClearance): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FinancialClearance $financialClearance): bool
    {
        return false;
    }
}
