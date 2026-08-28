<?php

namespace App\Actions\Staff;

use App\Models\Role;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateStaffUser
{
    /**
     * @param  array{name: string, email: string, role: string, is_active: bool}  $attributes
     */
    public function handle(User $staffUser, array $attributes): User
    {
        return DB::transaction(function () use ($staffUser, $attributes): User {
            $administratorRole = Role::query()
                ->where('slug', StaffRole::Administrator->value)
                ->lockForUpdate()
                ->sole();
            $targetRole = Role::query()
                ->where('slug', StaffRole::from($attributes['role'])->value)
                ->sole();
            $lockedStaffUser = User::query()
                ->whereKey($staffUser->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $removesActiveAdministrator = $lockedStaffUser->role_id === $administratorRole->id
                && $lockedStaffUser->is_active
                && (! $attributes['is_active'] || $targetRole->id !== $administratorRole->id);

            if ($removesActiveAdministrator && ! $this->anotherActiveAdministratorExists(
                $administratorRole,
                $lockedStaffUser,
            )) {
                throw ValidationException::withMessages([
                    $attributes['is_active'] ? 'role' : 'is_active' => 'The last active Administrator cannot be deactivated or assigned another role.',
                ]);
            }

            $lockedStaffUser->role_id = $targetRole->id;
            $lockedStaffUser->is_active = $attributes['is_active'];
            $lockedStaffUser->name = $attributes['name'];
            $lockedStaffUser->email = $attributes['email'];
            $lockedStaffUser->save();

            return $lockedStaffUser->refresh();
        });
    }

    private function anotherActiveAdministratorExists(Role $administratorRole, User $staffUser): bool
    {
        return User::query()
            ->whereBelongsTo($administratorRole)
            ->whereKeyNot($staffUser->getKey())
            ->where('is_active', true)
            ->exists();
    }
}
