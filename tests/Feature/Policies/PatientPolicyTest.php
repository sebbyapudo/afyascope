<?php

use App\Models\Patient;
use App\Models\User;
use App\Policies\PatientPolicy;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Receptionist may register Patients', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows('create', Patient::class))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('guests and inactive Receptionists cannot register Patients', function () {
    $inactiveReceptionist = User::factory()
        ->forRole(StaffRole::Receptionist)
        ->inactive()
        ->create();

    expect(Gate::forUser(null)->denies('create', Patient::class))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('create', Patient::class))->toBeTrue();
});

test('Patient viewing updating and deletion are not capabilities in this checkpoint', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create();
    $gate = Gate::forUser($receptionist);

    expect(method_exists(PatientPolicy::class, 'viewAny'))->toBeFalse()
        ->and(method_exists(PatientPolicy::class, 'view'))->toBeFalse()
        ->and(method_exists(PatientPolicy::class, 'update'))->toBeFalse()
        ->and(method_exists(PatientPolicy::class, 'delete'))->toBeFalse()
        ->and($gate->denies('view', $patient))->toBeTrue()
        ->and($gate->denies('update', $patient))->toBeTrue()
        ->and($gate->denies('delete', $patient))->toBeTrue();
});
