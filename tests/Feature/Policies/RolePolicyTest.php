<?php

use App\Models\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('administrator can view fixed roles', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $role = Role::query()->where('slug', StaffRole::Receptionist->value)->sole();
    $gate = Gate::forUser($administrator);

    expect($gate->allows('viewAny', Role::class))->toBeTrue()
        ->and($gate->allows('view', $role))->toBeTrue();
});

test('all other roles cannot view fixed roles', function (StaffRole $staffRole) {
    $actor = User::factory()->forRole($staffRole)->create();
    $role = Role::query()->where('slug', StaffRole::Receptionist->value)->sole();
    $gate = Gate::forUser($actor);

    expect($gate->denies('viewAny', Role::class))->toBeTrue()
        ->and($gate->denies('view', $role))->toBeTrue();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Management,
]);

test('fixed roles have no write or delete authorization capability', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $role = Role::query()->where('slug', StaffRole::Receptionist->value)->sole();
    $gate = Gate::forUser($administrator);

    expect(method_exists(RolePolicy::class, 'create'))->toBeFalse()
        ->and(method_exists(RolePolicy::class, 'update'))->toBeFalse()
        ->and(method_exists(RolePolicy::class, 'delete'))->toBeFalse()
        ->and($gate->denies('create', Role::class))->toBeTrue()
        ->and($gate->denies('update', $role))->toBeTrue()
        ->and($gate->denies('delete', $role))->toBeTrue();
});
