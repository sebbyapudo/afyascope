<?php

use App\Models\Consultation;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Doctor may view and create Consultations', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $consultation = Consultation::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Consultation::class))->toBe($allowed)
        ->and($gate->allows('view', $consultation))->toBe($allowed)
        ->and($gate->allows('create', Consultation::class))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, true],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('only the responsible Doctor may update an in-progress Consultation', function () {
    $responsibleDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()
        ->for($responsibleDoctor, 'doctor')
        ->create();

    expect(Gate::forUser($responsibleDoctor)->allows('update', $consultation))->toBeTrue()
        ->and(Gate::forUser($otherDoctor)->denies('update', $consultation))->toBeTrue();

    $consultation = Consultation::factory()
        ->for($responsibleDoctor, 'doctor')
        ->createFinalizedFixture();

    expect(Gate::forUser($responsibleDoctor)->denies('update', $consultation))->toBeTrue();
});

test('guests inactive Doctors and routine destructive mutations are denied', function () {
    $inactiveDoctor = User::factory()->forRole(StaffRole::Doctor)->inactive()->create();
    $activeDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($activeDoctor, 'doctor')->create();

    expect(Gate::forUser(null)->denies('viewAny', Consultation::class))->toBeTrue()
        ->and(Gate::forUser(null)->denies('create', Consultation::class))->toBeTrue()
        ->and(Gate::forUser($inactiveDoctor)->denies('viewAny', Consultation::class))->toBeTrue()
        ->and(Gate::forUser($inactiveDoctor)->denies('create', Consultation::class))->toBeTrue()
        ->and(Gate::forUser($activeDoctor)->denies('delete', $consultation))->toBeTrue()
        ->and(Gate::forUser($activeDoctor)->denies('restore', $consultation))->toBeTrue()
        ->and(Gate::forUser($activeDoctor)->denies('forceDelete', $consultation))->toBeTrue();
});
