<?php

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Accountant may view and record Payments', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $payment = Payment::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Payment::class))->toBe($allowed)
        ->and($gate->allows('view', $payment))->toBe($allowed)
        ->and($gate->allows('create', Payment::class))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('only Accountant may view Receipts', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $receipt = Receipt::factory()->create();

    expect(Gate::forUser($user)->allows('view', $receipt))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('guests inactive Accountants and routine mutations are denied', function () {
    $inactiveAccountant = User::factory()->forRole(StaffRole::Accountant)->inactive()->create();
    $payment = Payment::factory()->create();
    $receipt = Receipt::factory()->for($payment)->create();
    $activeAccountant = User::factory()->forRole(StaffRole::Accountant)->create();

    expect(Gate::forUser(null)->denies('viewAny', Payment::class))->toBeTrue()
        ->and(Gate::forUser(null)->denies('create', Payment::class))->toBeTrue()
        ->and(Gate::forUser(null)->denies('view', $receipt))->toBeTrue()
        ->and(Gate::forUser($inactiveAccountant)->denies('viewAny', Payment::class))->toBeTrue()
        ->and(Gate::forUser($inactiveAccountant)->denies('create', Payment::class))->toBeTrue()
        ->and(Gate::forUser($inactiveAccountant)->denies('view', $receipt))->toBeTrue()
        ->and(Gate::forUser($activeAccountant)->denies('update', $payment))->toBeTrue()
        ->and(Gate::forUser($activeAccountant)->denies('delete', $payment))->toBeTrue()
        ->and(Gate::forUser($activeAccountant)->denies('delete', $receipt))->toBeTrue();
});
