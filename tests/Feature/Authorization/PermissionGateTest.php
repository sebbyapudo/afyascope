<?php

use App\Models\Permission;
use App\Models\User;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('all six staff roles can pass the dashboard gate', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows(StaffPermission::DashboardView))->toBeTrue();
})->with(StaffRole::cases());

test('administrator and management retain audit visibility', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows(StaffPermission::AuditView))->toBeTrue();
})->with([
    StaffRole::Administrator,
    StaffRole::Management,
]);

test('operational roles do not receive audit visibility', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->denies(StaffPermission::AuditView))->toBeTrue();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
]);

test('an administrator has no blanket authorization bypass', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    expect(Gate::forUser($administrator)->denies('future.permission'))->toBeTrue()
        ->and(Gate::forUser($administrator)->denies('*'))->toBeTrue();
});

test('only Receptionist receives the patient and visit creation gates', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $gate = Gate::forUser($user);

    expect($gate->allows(StaffPermission::PatientsCreate))->toBe($allowed)
        ->and($gate->allows(StaffPermission::VisitsCreate))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('gate authorization follows database permissions instead of role names', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $dashboardPermission = Permission::query()
        ->where('slug', StaffPermission::DashboardView->value)
        ->sole();

    $administrator->role->permissions()->detach($dashboardPermission);
    $administrator->unsetRelation('role');

    expect(Gate::forUser($administrator)->denies(StaffPermission::DashboardView))->toBeTrue();
});

test('only Accountant receives the billing gates', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows(StaffPermission::BillingView))->toBe($allowed)
        ->and(Gate::forUser($user)->allows(StaffPermission::BillingCreate))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('only Accountant receives the payment gates', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows(StaffPermission::PaymentsView))->toBe($allowed)
        ->and(Gate::forUser($user)->allows(StaffPermission::PaymentsCreate))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('only Accountant receives the financial-clearance gates', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows(StaffPermission::ClearanceView))->toBe($allowed)
        ->and(Gate::forUser($user)->allows(StaffPermission::ClearanceCreate))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('only Receptionist receives the check-in gates', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows(StaffPermission::CheckInView))->toBe($allowed)
        ->and(Gate::forUser($user)->allows(StaffPermission::CheckInCreate))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);
