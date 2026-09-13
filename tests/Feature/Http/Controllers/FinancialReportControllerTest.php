<?php

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the bookmarkable aggregate Financial Report for each authorized role', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();
    $patient = Patient::factory()->create(['first_name' => 'Sensitive', 'last_name' => 'Patient']);
    $this->travelTo('2026-09-12 10:00:00');
    $visit = Visit::factory()->for($patient)->create();
    $bill = Bill::factory()->for($visit)->create();
    BillItem::factory()
        ->for($bill)
        ->for(ServiceCatalogItem::factory()->state(['unit_price_minor' => 75_000]))
        ->create();
    AuditLog::factory()->create(['metadata' => ['secret' => 'Private raw audit metadata']]);
    $auditCount = AuditLog::query()->count();

    $this->actingAs($user)
        ->get(route('reports.financial.index', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/financial')
            ->where('report.period', [
                'fromDate' => '2026-09-01',
                'throughDate' => '2026-09-30',
                'timezone' => 'UTC',
            ])
            ->where('report.currency', 'KES')
            ->where('report.overall.billCount', 1)
            ->where('report.overall.billedAmountMinor', 75_000)
            ->where('auth.capabilities.viewFinancialReports', true)
            ->missing('report.patient')
            ->missing('report.visit')
            ->missing('report.clinical')
            ->missing('report.audit'));

    expect(AuditLog::query()->count())->toBe($auditCount);
})->with([StaffRole::Accountant, StaffRole::Administrator, StaffRole::Management]);

it('uses the current calendar month as its deterministic default period', function () {
    $this->travelTo('2026-09-12 10:00:00');
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();

    $this->actingAs($accountant)
        ->get(route('reports.financial.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.period.fromDate', '2026-09-01')
            ->where('report.period.throughDate', '2026-09-30')
            ->where('report.period.timezone', 'UTC'));
});

it('rejects invalid or incomplete financial report periods cleanly', function (array $query, array $errors) {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();

    $this->actingAs($accountant)
        ->from(route('reports.financial.index'))
        ->get(route('reports.financial.index', $query))
        ->assertRedirect(route('reports.financial.index'))
        ->assertSessionHasErrors($errors);
})->with([
    'reversed period' => [['date_from' => '2026-09-30', 'date_to' => '2026-09-01'], ['date_to']],
    'invalid date format' => [['date_from' => '09/01/2026', 'date_to' => '2026-09-30'], ['date_from']],
    'missing through date' => [['date_from' => '2026-09-01'], ['date_to']],
]);

it('denies direct access to unauthorized financial reporting roles', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();

    $this->actingAs($user)->get(route('reports.financial.index'))->assertForbidden();
})->with([StaffRole::Receptionist, StaffRole::Doctor, StaffRole::Nurse]);

it('redirects guests and inactive authorized-role users to login', function (StaffRole $role) {
    $this->get(route('reports.financial.index'))->assertRedirect(route('login'));
    $user = User::factory()->forRole($role)->inactive()->create();

    $this->actingAs($user)
        ->get(route('reports.financial.index'))
        ->assertRedirect(route('login'));
})->with([StaffRole::Accountant, StaffRole::Administrator, StaffRole::Management]);
