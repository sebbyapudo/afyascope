<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (StaffPermission::cases() as $permission) {
                Permission::query()->updateOrCreate(
                    ['slug' => $permission->value],
                    ['name' => $permission->displayName()],
                );
            }

            foreach (StaffRole::cases() as $staffRole) {
                $role = Role::query()->updateOrCreate(
                    ['slug' => $staffRole->value],
                    ['name' => $staffRole->displayName()],
                );

                $permissionIds = Permission::query()
                    ->whereIn('slug', array_map(
                        static fn (StaffPermission $permission): string => $permission->value,
                        $staffRole->permissions(),
                    ))
                    ->pluck('id');

                $role->permissions()->sync($permissionIds);
            }
        });
    }
}
