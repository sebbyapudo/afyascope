<?php

use App\Models\Permission;
use App\Models\User;
use App\Policies\UserPolicy;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('administrator can view and manage staff users', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $staffUser = User::factory()->forRole(StaffRole::Receptionist)->create();
    $gate = Gate::forUser($administrator);

    expect($gate->allows('viewAny', User::class))->toBeTrue()
        ->and($gate->allows('view', $staffUser))->toBeTrue()
        ->and($gate->allows('create', User::class))->toBeTrue()
        ->and($gate->allows('update', $staffUser))->toBeTrue();
});

test('non-administrator roles cannot view or manage staff users', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $staffUser = User::factory()->forRole(StaffRole::Receptionist)->create();
    $gate = Gate::forUser($actor);

    expect($gate->denies('viewAny', User::class))->toBeTrue()
        ->and($gate->denies('view', $staffUser))->toBeTrue()
        ->and($gate->denies('create', User::class))->toBeTrue()
        ->and($gate->denies('update', $staffUser))->toBeTrue();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Management,
]);

test('administrator authorization is removed when the underlying permission is removed', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $staffUser = User::factory()->forRole(StaffRole::Receptionist)->create();
    $managePermission = Permission::query()
        ->where('slug', StaffPermission::UsersManage->value)
        ->sole();

    $administrator->role->permissions()->detach($managePermission);
    $administrator->unsetRelation('role');

    expect(Gate::forUser($administrator)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($administrator)->denies('create', User::class))->toBeTrue()
        ->and(Gate::forUser($administrator)->denies('update', $staffUser))->toBeTrue();
});

test('staff-user deletion is not an authorization capability', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $staffUser = User::factory()->forRole(StaffRole::Receptionist)->create();

    expect(method_exists(UserPolicy::class, 'delete'))->toBeFalse()
        ->and(Gate::forUser($administrator)->denies('delete', $staffUser))->toBeTrue();
});
