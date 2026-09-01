<?php

use App\Models\FinancialClearance;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Accountant may view and grant financial clearance', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $financialClearance = FinancialClearance::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', FinancialClearance::class))->toBe($allowed)
        ->and($gate->allows('view', $financialClearance))->toBe($allowed)
        ->and($gate->allows('create', FinancialClearance::class))->toBe($allowed);
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
    $activeAccountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $financialClearance = FinancialClearance::factory()->create();

    expect(Gate::forUser(null)->denies('viewAny', FinancialClearance::class))->toBeTrue()
        ->and(Gate::forUser(null)->denies('create', FinancialClearance::class))->toBeTrue()
        ->and(Gate::forUser($inactiveAccountant)->denies('viewAny', FinancialClearance::class))->toBeTrue()
        ->and(Gate::forUser($inactiveAccountant)->denies('create', FinancialClearance::class))->toBeTrue()
        ->and(Gate::forUser($activeAccountant)->denies('update', $financialClearance))->toBeTrue()
        ->and(Gate::forUser($activeAccountant)->denies('delete', $financialClearance))->toBeTrue();
});
