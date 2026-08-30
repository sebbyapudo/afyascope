<?php

use App\Models\User;
use App\Models\Visit;
use App\Policies\VisitPolicy;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('only Receptionist may create Visits', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();

    expect(Gate::forUser($user)->allows('create', Visit::class))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

test('guests and inactive Receptionists cannot create Visits', function () {
    $inactiveReceptionist = User::factory()
        ->forRole(StaffRole::Receptionist)
        ->inactive()
        ->create();

    expect(Gate::forUser(null)->denies('create', Visit::class))->toBeTrue()
        ->and(Gate::forUser($inactiveReceptionist)->denies('create', Visit::class))->toBeTrue();
});

test('Visit viewing updating and deletion are not capabilities in this checkpoint', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $visit = Visit::factory()->create();
    $gate = Gate::forUser($receptionist);

    expect(method_exists(VisitPolicy::class, 'viewAny'))->toBeFalse()
        ->and(method_exists(VisitPolicy::class, 'view'))->toBeFalse()
        ->and(method_exists(VisitPolicy::class, 'update'))->toBeFalse()
        ->and(method_exists(VisitPolicy::class, 'delete'))->toBeFalse()
        ->and($gate->denies('view', $visit))->toBeTrue()
        ->and($gate->denies('update', $visit))->toBeTrue()
        ->and($gate->denies('delete', $visit))->toBeTrue();
});
