<?php

use App\Models\User;
use App\Models\VisitCheckIn;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Receptionist may view and create Visit check-ins', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $visitCheckIn = VisitCheckIn::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', VisitCheckIn::class))->toBe($allowed)
        ->and($gate->allows('view', $visitCheckIn))->toBe($allowed)
        ->and($gate->allows('create', VisitCheckIn::class))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('guests inactive Receptionists and routine mutations are denied', function () {
    $inactiveReceptionist = User::factory()->forRole(StaffRole::Receptionist)->inactive()->create();
    $activeReceptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $visitCheckIn = VisitCheckIn::factory()->create();

    expect(Gate::forUser(null)->denies('viewAny', VisitCheckIn::class))->toBeTrue()
        ->and(Gate::forUser(null)->denies('create', VisitCheckIn::class))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('viewAny', VisitCheckIn::class))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('create', VisitCheckIn::class))->toBeTrue()
        ->and(Gate::forUser($activeReceptionist)->denies('update', $visitCheckIn))->toBeTrue()
        ->and(Gate::forUser($activeReceptionist)->denies('delete', $visitCheckIn))->toBeTrue();
});
