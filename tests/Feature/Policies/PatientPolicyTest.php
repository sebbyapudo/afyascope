<?php

use App\Models\Patient;
use App\Models\User;
use App\Policies\PatientPolicy;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Receptionist receives Patient registry capabilities', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $patient = Patient::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Patient::class))->toBe($allowed)
        ->and($gate->allows('view', $patient))->toBe($allowed)
        ->and($gate->allows('create', Patient::class))->toBe($allowed)
        ->and($gate->allows('update', $patient))->toBe($allowed)
        ->and($gate->denies('delete', $patient))->toBeTrue();
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('guests and inactive Receptionists cannot access Patient registry capabilities', function () {
    $inactiveReceptionist = User::factory()
        ->forRole(StaffRole::Receptionist)
        ->inactive()
        ->create();

    $patient = Patient::factory()->create();
    $guestGate = Gate::forUser(null);
    $inactiveGate = Gate::forUser($inactiveReceptionist);

    expect(method_exists(PatientPolicy::class, 'delete'))->toBeFalse()
        ->and($guestGate->denies('viewAny', Patient::class))->toBeTrue()
        ->and($guestGate->denies('create', Patient::class))->toBeTrue()
        ->and($inactiveGate->denies('view', $patient))->toBeTrue()
        ->and($inactiveGate->denies('update', $patient))->toBeTrue();
});
