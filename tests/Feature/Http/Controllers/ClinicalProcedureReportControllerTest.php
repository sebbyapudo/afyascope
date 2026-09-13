<?php

use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the bookmarkable aggregate Clinical Procedure Report for each authorized role', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();
    $patient = Patient::factory()->create([
        'first_name' => 'NeverExposeClinicalPatient',
        'last_name' => 'InAggregateReport',
    ]);
    $this->travelTo('2026-09-12 10:00:00');
    $consultation = Consultation::factory()->create([
        'presenting_complaint' => 'Never expose this clinical narrative.',
    ]);
    $consultation->visit->update(['patient_id' => $patient->getKey()]);
    AuditLog::factory()->create(['metadata' => ['secret' => 'Never expose raw audit metadata.']]);
    $auditCount = AuditLog::query()->count();

    $response = $this->actingAs($user)
        ->get(route('reports.clinical.index', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/clinical')
        ->where('report.period', [
            'fromDate' => '2026-09-01',
            'throughDate' => '2026-09-30',
            'timezone' => 'UTC',
        ])
        ->where('report.consultationDecision.consultationsStarted', 1)
        ->where('auth.capabilities.viewClinicalReports', true)
        ->missing('report.patient')
        ->missing('report.visit')
        ->missing('report.financial')
        ->missing('report.audit'));

    $response->assertDontSee('NeverExposeClinicalPatient')
        ->assertDontSee('Never expose this clinical narrative.')
        ->assertDontSee('Never expose raw audit metadata.');
    expect(AuditLog::query()->count())->toBe($auditCount);
})->with([StaffRole::Doctor, StaffRole::Administrator, StaffRole::Management]);

it('uses the current calendar month as its deterministic default period', function () {
    $this->travelTo('2026-09-12 10:00:00');
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    $this->actingAs($doctor)
        ->get(route('reports.clinical.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.period.fromDate', '2026-09-01')
            ->where('report.period.throughDate', '2026-09-30')
            ->where('report.period.timezone', 'UTC'));
});

it('rejects invalid or incomplete clinical report periods cleanly', function (array $query, array $errors) {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    $this->actingAs($doctor)
        ->from(route('reports.clinical.index'))
        ->get(route('reports.clinical.index', $query))
        ->assertRedirect(route('reports.clinical.index'))
        ->assertSessionHasErrors($errors);
})->with([
    'reversed period' => [['date_from' => '2026-09-30', 'date_to' => '2026-09-01'], ['date_to']],
    'invalid date format' => [['date_from' => '09/01/2026', 'date_to' => '2026-09-30'], ['date_from']],
    'missing through date' => [['date_from' => '2026-09-01'], ['date_to']],
]);

it('denies direct access to unauthorized clinical reporting roles', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();

    $this->actingAs($user)->get(route('reports.clinical.index'))->assertForbidden();
})->with([StaffRole::Receptionist, StaffRole::Accountant, StaffRole::Nurse]);

it('redirects guests and inactive authorized-role users to login', function (StaffRole $role) {
    $this->get(route('reports.clinical.index'))->assertRedirect(route('login'));
    $user = User::factory()->forRole($role)->inactive()->create();

    $this->actingAs($user)
        ->get(route('reports.clinical.index'))
        ->assertRedirect(route('login'));
})->with([StaffRole::Doctor, StaffRole::Administrator, StaffRole::Management]);
