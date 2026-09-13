<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the bookmarkable Operational Report for each authorized role', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();
    $this->travelTo('2026-09-12 10:00:00');
    Visit::factory()->create();
    $auditCount = AuditLog::query()->count();

    $this->actingAs($user)
        ->get(route('reports.operational.index', [
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/operational')
            ->where('report.period', [
                'fromDate' => '2026-09-01',
                'throughDate' => '2026-09-30',
                'timezone' => 'UTC',
            ])
            ->where('report.metrics.visits.occurred', 1)
            ->where('auth.capabilities.viewOperationalReports', true)
            ->missing('report.patient')
            ->missing('report.financial')
            ->missing('report.audit'));

    expect(AuditLog::query()->count())->toBe($auditCount);
})->with([
    StaffRole::Receptionist,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('uses the current calendar month as its deterministic default period', function () {
    $this->travelTo('2026-09-12 10:00:00');
    $management = User::factory()->forRole(StaffRole::Management)->create();

    $this->actingAs($management)
        ->get(route('reports.operational.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.period.fromDate', '2026-09-01')
            ->where('report.period.throughDate', '2026-09-30')
            ->where('report.period.timezone', 'UTC'));
});

it('rejects invalid or incomplete report periods cleanly', function (array $query, array $errors) {
    $management = User::factory()->forRole(StaffRole::Management)->create();

    $this->actingAs($management)
        ->from(route('reports.operational.index'))
        ->get(route('reports.operational.index', $query))
        ->assertRedirect(route('reports.operational.index'))
        ->assertSessionHasErrors($errors);
})->with([
    'reversed period' => [
        ['date_from' => '2026-09-30', 'date_to' => '2026-09-01'],
        ['date_to'],
    ],
    'invalid date format' => [
        ['date_from' => '09/01/2026', 'date_to' => '2026-09-30'],
        ['date_from'],
    ],
    'missing through date' => [
        ['date_from' => '2026-09-01'],
        ['date_to'],
    ],
]);

it('denies direct access to unauthorized operational roles', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();

    $this->actingAs($user)
        ->get(route('reports.operational.index'))
        ->assertForbidden();
})->with([
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
]);

it('redirects guests and inactive authorized-role users to login', function (StaffRole $role) {
    $this->get(route('reports.operational.index'))->assertRedirect(route('login'));
    $user = User::factory()->forRole($role)->inactive()->create();

    $this->actingAs($user)
        ->get(route('reports.operational.index'))
        ->assertRedirect(route('login'));
})->with([
    StaffRole::Receptionist,
    StaffRole::Administrator,
    StaffRole::Management,
]);
