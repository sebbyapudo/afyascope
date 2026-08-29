<?php

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('allows an Administrator to view a newest-first sanitized audit history', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $subject = User::factory()->forRole(StaffRole::Nurse)->create(['name' => 'Nurse Subject']);
    $olderAuditLog = AuditLog::factory()->create([
        'actor_id' => $administrator->id,
        'subject_id' => $subject->id,
        'before_values' => ['name' => 'Old name'],
        'after_values' => ['name' => 'Intermediate name'],
        'metadata' => ['password_reset_token' => 'must-not-be-exposed'],
        'created_at' => now()->subMinute(),
    ]);
    $newerAuditLog = AuditLog::factory()->create([
        'actor_id' => $administrator->id,
        'action' => AuditAction::StaffUpdated,
        'subject_id' => $subject->id,
        'before_values' => ['is_active' => true],
        'after_values' => ['is_active' => false],
        'created_at' => now(),
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit-logs/index')
            ->has('auditLogs.data', 2)
            ->where('auditLogs.data.0', [
                'id' => $newerAuditLog->id,
                'occurredAt' => $newerAuditLog->created_at->toIso8601String(),
                'actor' => [
                    'id' => $administrator->id,
                    'name' => $administrator->name,
                    'email' => $administrator->email,
                ],
                'action' => [
                    'value' => AuditAction::StaffUpdated->value,
                    'label' => AuditAction::StaffUpdated->displayName(),
                ],
                'subject' => [
                    'type' => 'User',
                    'id' => $subject->id,
                    'label' => 'Nurse Subject',
                ],
                'changes' => [[
                    'field' => 'is_active',
                    'label' => 'Account status',
                    'before' => true,
                    'after' => false,
                ]],
            ])
            ->where('auditLogs.data.1.id', $olderAuditLog->id)
            ->where('auditLogs.pagination.total', 2)
        );
});

it('represents a bootstrap audit entry without fabricating an actor', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $auditLog = AuditLog::factory()->create([
        'actor_id' => null,
        'action' => AuditAction::AdministratorBootstrapped,
        'subject_id' => $administrator->id,
        'before_values' => null,
        'after_values' => ['name' => $administrator->name],
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditLogs.data.0.id', $auditLog->id)
            ->where('auditLogs.data.0.actor', null)
            ->where('auditLogs.data.0.subject.id', $administrator->id)
        );
});

it('allows Management to view audit history', function () {
    $managementUser = User::factory()->forRole(StaffRole::Management)->create();

    $this->actingAs($managementUser)
        ->get(route('audit-logs.index'))
        ->assertInertia(fn (Assert $page) => $page->component('audit-logs/index'));
});

it('forbids operational roles from viewing audit history', function (StaffRole $staffRole) {
    $staffUser = User::factory()->forRole($staffRole)->create();

    $this->actingAs($staffUser)
        ->get(route('audit-logs.index'))
        ->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
]);

it('redirects guests away from audit history', function () {
    $this->get(route('audit-logs.index'))->assertRedirect(route('login'));
});

it('paginates audit history at twenty-five entries', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $subject = User::factory()->create();
    AuditLog::factory()->count(26)->create([
        'actor_id' => $administrator->id,
        'subject_id' => $subject->id,
    ]);

    $this->actingAs($administrator)
        ->get(route('audit-logs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auditLogs.data', 25)
            ->where('auditLogs.pagination.currentPage', 1)
            ->where('auditLogs.pagination.lastPage', 2)
            ->where('auditLogs.pagination.total', 26)
        );
});

it('exposes no mutable audit routes', function () {
    $routeCollection = collect(Route::getRoutes()->getRoutes());

    expect(Route::has('audit-logs.index'))->toBeTrue()
        ->and(Route::has('audit-logs.store'))->toBeFalse()
        ->and(Route::has('audit-logs.update'))->toBeFalse()
        ->and(Route::has('audit-logs.destroy'))->toBeFalse()
        ->and($routeCollection->contains(
            fn (Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'audit-logs')
                && array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) !== [],
        ))->toBeFalse();
});
