<?php

use App\Models\Bill;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Accountant may view Bills', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $bill = Bill::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Bill::class))->toBe($allowed)
        ->and($gate->allows('view', $bill))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('only Accountant may create Bills', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows('create', Bill::class))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('guests and inactive Accountants cannot view Bills', function () {
    $inactiveAccountant = User::factory()
        ->forRole(StaffRole::Accountant)
        ->inactive()
        ->create();
    $bill = Bill::factory()->create();

    expect(Gate::forUser(null)->denies('viewAny', Bill::class))->toBeTrue()
        ->and(Gate::forUser(null)->denies('view', $bill))->toBeTrue()
        ->and(Gate::forUser(null)->denies('create', Bill::class))->toBeTrue()
        ->and(Gate::forUser($inactiveAccountant)->denies('viewAny', Bill::class))->toBeTrue()
        ->and(Gate::forUser($inactiveAccountant)->denies('view', $bill))->toBeTrue()
        ->and(Gate::forUser($inactiveAccountant)->denies('create', Bill::class))->toBeTrue();
});

test('Bill writes are not authorized in this foundation checkpoint', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = Bill::factory()->create();
    $gate = Gate::forUser($accountant);

    expect($gate->denies('update', $bill))->toBeTrue()
        ->and($gate->denies('delete', $bill))->toBeTrue();
});
