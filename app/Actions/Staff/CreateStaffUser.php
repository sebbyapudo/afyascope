<?php

namespace App\Actions\Staff;

use App\Models\Role;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class CreateStaffUser
{
    /**
     * @param  array{name: string, email: string, role: string, is_active: bool}  $attributes
     */
    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $staffRole = StaffRole::from($attributes['role']);
            $role = Role::query()->where('slug', $staffRole->value)->sole();

            $user = new User;
            $user->role_id = $role->id;
            $user->is_active = $attributes['is_active'];
            $user->name = $attributes['name'];
            $user->email = $attributes['email'];
            $user->password = Hash::make(Str::password(64));
            $user->save();

            $status = Password::broker()->sendResetLink([
                'email' => $user->email,
            ]);

            if ($status !== Password::RESET_LINK_SENT) {
                throw new RuntimeException('The staff account invitation could not be sent.');
            }

            return $user;
        });
    }
}
