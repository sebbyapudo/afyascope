<?php

use App\Models\Role;
use App\Models\User;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('a user belongs to exactly one required role', function () {
    $user = User::factory()->forRole(StaffRole::Doctor)->create();

    expect($user->role)->toBeInstanceOf(Role::class)
        ->and($user->role->slug)->toBe('doctor')
        ->and(method_exists($user, 'roles'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'role_id'))->toBeTrue()
        ->and(Schema::hasTable('role_user'))->toBeFalse();
});

test('the database rejects users without a role', function () {
    expect(fn () => DB::table('users')->insert([
        'name' => 'No Role',
        'email' => 'no-role@example.com',
        'password' => 'not-a-real-password-hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('the database rejects users referencing an unknown role', function () {
    expect(fn () => DB::table('users')->insert([
        'role_id' => 999999,
        'name' => 'Unknown Role',
        'email' => 'unknown-role@example.com',
        'password' => 'not-a-real-password-hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('users do not support direct permissions', function () {
    $user = User::factory()->create();

    expect(method_exists($user, 'permissions'))->toBeFalse()
        ->and(Schema::hasTable('permission_user'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'permission_id'))->toBeFalse();
});

test('role and permission input cannot be mass assigned to users', function () {
    $user = User::factory()->forRole(StaffRole::Receptionist)->create();
    $administratorRoleId = Role::query()
        ->where('slug', StaffRole::Administrator->value)
        ->soleValue('id');

    $user->fill([
        'role_id' => $administratorRoleId,
        'permission_id' => 1,
        'permissions' => [StaffPermission::AuditView->value],
    ])->save();

    expect($user->fresh()->role->slug)->toBe(StaffRole::Receptionist->value)
        ->and(array_key_exists('permission_id', $user->getAttributes()))->toBeFalse()
        ->and(array_key_exists('permissions', $user->getAttributes()))->toBeFalse();
});

test('permission checks follow the canonical role mappings', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $management = User::factory()->forRole(StaffRole::Management)->create();

    expect($receptionist->hasPermission(StaffPermission::DashboardView))->toBeTrue()
        ->and($receptionist->hasPermission(StaffPermission::AuditView))->toBeFalse()
        ->and($administrator->hasPermission(StaffPermission::DashboardView))->toBeTrue()
        ->and($administrator->hasPermission(StaffPermission::UsersView))->toBeTrue()
        ->and($administrator->hasPermission(StaffPermission::UsersManage))->toBeTrue()
        ->and($administrator->hasPermission(StaffPermission::RolesView))->toBeTrue()
        ->and($administrator->hasPermission(StaffPermission::AuditView))->toBeTrue()
        ->and($management->hasPermission(StaffPermission::DashboardView))->toBeTrue()
        ->and($management->hasPermission(StaffPermission::AuditView))->toBeTrue()
        ->and($management->hasPermission(StaffPermission::UsersView))->toBeFalse()
        ->and($management->hasPermission(StaffPermission::UsersManage))->toBeFalse()
        ->and($management->hasPermission(StaffPermission::RolesView))->toBeFalse();
});

test('unknown permissions are denied without an administrator bypass', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    expect($administrator->hasPermission('unknown.permission'))->toBeFalse()
        ->and($administrator->role->hasPermission('*'))->toBeFalse();
});
