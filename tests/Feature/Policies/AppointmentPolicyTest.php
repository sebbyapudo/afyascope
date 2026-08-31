<?php

use App\Models\Appointment;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Receptionist receives Appointment capabilities', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $appointment = Appointment::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Appointment::class))->toBe($allowed)
        ->and($gate->allows('view', $appointment))->toBe($allowed)
        ->and($gate->allows('create', Appointment::class))->toBe($allowed)
        ->and($gate->allows('update', $appointment))->toBe($allowed)
        ->and($gate->denies('delete', $appointment))->toBeTrue();
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('guests and inactive Receptionists cannot access Appointment capabilities', function () {
    $inactiveReceptionist = User::factory()
        ->forRole(StaffRole::Receptionist)
        ->inactive()
        ->create();
    $appointment = Appointment::factory()->create();

    expect(method_exists(AppointmentPolicy::class, 'delete'))->toBeFalse()
        ->and(Gate::forUser(null)->denies('viewAny', Appointment::class))->toBeTrue()
        ->and(Gate::forUser(null)->denies('create', Appointment::class))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('view', $appointment))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('update', $appointment))->toBeTrue();
});
