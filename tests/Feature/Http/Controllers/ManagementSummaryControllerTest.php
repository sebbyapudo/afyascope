<?php

use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\User;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

it('renders a bookmarkable private Management Summary for each authorized role', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();
    $this->travelTo('2026-09-12 10:00:00');
    $consultation = Consultation::factory()->create([
        'presenting_complaint' => 'Never expose this management clinical narrative.',
    ]);
    $consultation->visit->patient->update([
        'first_name' => 'NeverExposeManagementPatient',
        'last_name' => 'AggregateOnly',
    ]);
    AuditLog::factory()->create([
        'metadata' => ['secret' => 'Never expose management audit metadata.'],
    ]);
    $auditCount = AuditLog::query()->count();
    $visitReference = $consultation->visit->visit_number;

    $response = $this->actingAs($user)
        ->get(route('reports.management.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/management')
        ->where('summary.period', [
            'fromDate' => '2026-09-01',
            'throughDate' => '2026-09-30',
            'timezone' => 'UTC',
        ])
        ->where('summary.currency', 'KES')
        ->where('summary.visits.occurred', 1)
        ->where('summary.clinical.consultationsStarted', 1)
        ->where('auth.capabilities.viewManagementReports', true)
        ->missing('summary.patient')
        ->missing('summary.visit')
        ->missing('summary.audit')
        ->missing('summary.narrative')
        ->missing('summary.vitals'));

    $response
        ->assertDontSee('NeverExposeManagementPatient')
        ->assertDontSee('Never expose this management clinical narrative.')
        ->assertDontSee('Never expose management audit metadata.')
        ->assertDontSee($visitReference);
    expect(AuditLog::query()->count())->toBe($auditCount);
})->with([
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('uses the current calendar month as its deterministic default period', function () {
    $this->travelTo('2026-09-12 10:00:00');
    $management = User::factory()->forRole(StaffRole::Management)->create();

    $this->actingAs($management)
        ->get(route('reports.management.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.period.fromDate', '2026-09-01')
            ->where('summary.period.throughDate', '2026-09-30')
            ->where('summary.period.timezone', 'UTC'));
});

it('rejects invalid or incomplete management summary periods cleanly', function (array $query, array $errors) {
    $management = User::factory()->forRole(StaffRole::Management)->create();

    $this->actingAs($management)
        ->from(route('reports.management.index'))
        ->get(route('reports.management.index', $query))
        ->assertRedirect(route('reports.management.index'))
        ->assertSessionHasErrors($errors);
})->with([
    'reversed period' => [
        ['from' => '2026-09-30', 'to' => '2026-09-01'],
        ['to'],
    ],
    'invalid date format' => [
        ['from' => '09/01/2026', 'to' => '2026-09-30'],
        ['from'],
    ],
    'missing through date' => [
        ['from' => '2026-09-01'],
        ['to'],
    ],
    'missing start date' => [
        ['to' => '2026-09-30'],
        ['from'],
    ],
]);

it('denies direct Management Summary access to operational roles', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();

    $this->actingAs($user)
        ->get(route('reports.management.index'))
        ->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
]);

it('redirects guests and inactive authorized-role users to login', function (StaffRole $role) {
    $this->get(route('reports.management.index'))->assertRedirect(route('login'));
    $user = User::factory()->forRole($role)->inactive()->create();

    $this->actingAs($user)
        ->get(route('reports.management.index'))
        ->assertRedirect(route('login'));
})->with([
    StaffRole::Administrator,
    StaffRole::Management,
]);
