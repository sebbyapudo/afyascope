<?php

use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

it('allows only active Administrators to view and manage the service catalog', function (StaffRole $role, bool $allowed) {
    $actor = User::factory()->forRole($role)->create();
    $service = ServiceCatalogItem::factory()->create();
    $gate = Gate::forUser($actor);

    expect($gate->allows('viewAny', ServiceCatalogItem::class))->toBe($allowed)
        ->and($gate->allows('view', $service))->toBe($allowed)
        ->and($gate->allows('create', ServiceCatalogItem::class))->toBe($allowed)
        ->and($gate->allows('update', $service))->toBe($allowed)
        ->and($gate->denies('delete', $service))->toBeTrue();
})->with([
    'Administrator' => [StaffRole::Administrator, true],
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Management' => [StaffRole::Management, false],
]);

it('denies an inactive Administrator', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->inactive()->create();
    $service = ServiceCatalogItem::factory()->create();

    expect(Gate::forUser($administrator)->denies('viewAny', ServiceCatalogItem::class))->toBeTrue()
        ->and(Gate::forUser($administrator)->denies('update', $service))->toBeTrue();
});
