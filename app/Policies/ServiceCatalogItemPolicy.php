<?php

namespace App\Policies;

use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\StaffPermission;

class ServiceCatalogItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StaffPermission::ServicesManage);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceCatalogItem $serviceCatalogItem): bool
    {
        return $user->hasPermission(StaffPermission::ServicesManage);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission(StaffPermission::ServicesManage);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceCatalogItem $serviceCatalogItem): bool
    {
        return $user->hasPermission(StaffPermission::ServicesManage);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceCatalogItem $serviceCatalogItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ServiceCatalogItem $serviceCatalogItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ServiceCatalogItem $serviceCatalogItem): bool
    {
        return false;
    }
}
