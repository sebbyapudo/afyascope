<?php

use App\Models\User;
use App\Models\Visit;
use App\Policies\VisitPolicy;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Receptionist may view and create Visits', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $visit = Visit::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Visit::class))->toBe($allowed)
        ->and($gate->allows('view', $visit))->toBe($allowed)
        ->and($gate->allows('create', Visit::class))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('guests and inactive Receptionists cannot view or create Visits', function () {
    $inactiveReceptionist = User::factory()
        ->forRole(StaffRole::Receptionist)
        ->inactive()
        ->create();

    $visit = Visit::factory()->create();

    expect(Gate::forUser(null)->denies('viewAny', Visit::class))->toBeTrue()
        ->and(Gate::forUser(null)->denies('view', $visit))->toBeTrue()
        ->and(Gate::forUser(null)->denies('create', Visit::class))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('viewAny', Visit::class))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('view', $visit))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('create', Visit::class))->toBeTrue();
});

test('Visit updating and deletion are not capabilities in this checkpoint', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $visit = Visit::factory()->create();
    $gate = Gate::forUser($receptionist);

    expect(method_exists(VisitPolicy::class, 'update'))->toBeFalse()
        ->and(method_exists(VisitPolicy::class, 'delete'))->toBeFalse()
        ->and($gate->denies('update', $visit))->toBeTrue()
        ->and($gate->denies('delete', $visit))->toBeTrue();
});
