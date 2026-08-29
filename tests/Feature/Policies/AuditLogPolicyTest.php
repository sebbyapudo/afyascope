<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

test('Administrator and Management can view audit history', function (StaffRole $staffRole) {
    $viewer = User::factory()->forRole($staffRole)->create();
    $auditLog = AuditLog::factory()->create();
    $gate = Gate::forUser($viewer);

    expect($gate->allows('viewAny', AuditLog::class))->toBeTrue()
        ->and($gate->allows('view', $auditLog))->toBeTrue();
})->with([
    StaffRole::Administrator,
    StaffRole::Management,
]);

test('operational roles cannot view audit history', function (StaffRole $staffRole) {
    $staffUser = User::factory()->forRole($staffRole)->create();
    $auditLog = AuditLog::factory()->create();
    $gate = Gate::forUser($staffUser);

    expect($gate->denies('viewAny', AuditLog::class))->toBeTrue()
        ->and($gate->denies('view', $auditLog))->toBeTrue();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
]);

test('audit history has no mutable policy capabilities', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $auditLog = AuditLog::factory()->create();
    $gate = Gate::forUser($administrator);

    expect(method_exists(AuditLogPolicy::class, 'create'))->toBeFalse()
        ->and(method_exists(AuditLogPolicy::class, 'update'))->toBeFalse()
        ->and(method_exists(AuditLogPolicy::class, 'delete'))->toBeFalse()
        ->and($gate->denies('create', AuditLog::class))->toBeTrue()
        ->and($gate->denies('update', $auditLog))->toBeTrue()
        ->and($gate->denies('delete', $auditLog))->toBeTrue();
});
