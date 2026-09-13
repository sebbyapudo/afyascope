<?php

use App\Actions\Reporting\BuildFinancialReport;
use App\Actions\Reporting\ReportingPeriod;
use App\AuditAction;
use App\BillStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

it('reports immutable consultation and procedure snapshots without exposing operational details', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $consultationService = ServiceCatalogItem::factory()->create([
        'name' => 'Private consultation name',
        'unit_price_minor' => 1_000_000,
    ]);
    $procedureService = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Private procedure name',
        'unit_price_minor' => 2_500_050,
    ]);

    $this->travelTo('2026-03-31 23:59:59');
    $handoff = ProcedureBillingHandoff::factory()
        ->for($procedureService, 'serviceCatalogItem')
        ->createAuthoritativeDecisionFixture();

    $this->travelTo('2026-04-10 10:00:00');
    $consultationBill = Bill::factory()->for(Visit::factory())->create();
    BillItem::factory()->for($consultationBill)->for($consultationService)->create();
    $consultationPayment = Payment::factory()
        ->for($consultationBill)
        ->for($accountant, 'recordedBy')
        ->create();
    Receipt::factory()->for($consultationPayment)->create();
    $consultationBill->status = BillStatus::Paid;
    $consultationBill->save();
    FinancialClearance::factory()
        ->for($consultationBill)
        ->for($accountant, 'grantedBy')
        ->create();

    $procedureBill = Bill::factory()->procedure($handoff)->create();
    BillItem::factory()->for($procedureBill)->for($procedureService)->create();

    $billItemSnapshots = BillItem::query()->orderBy('id')->get()
        ->map(fn (BillItem $billItem): array => $billItem->getAttributes())
        ->all();

    $consultationService->update([
        'name' => 'Renamed consultation',
        'unit_price_minor' => 1_500_000,
        'is_active' => false,
    ]);
    $procedureService->update([
        'name' => 'Renamed procedure',
        'unit_price_minor' => 3_000_000,
        'is_active' => false,
    ]);
    AuditLog::factory()->create([
        'action' => AuditAction::ServiceUpdated,
        'metadata' => ['secret' => 'Private audit metadata'],
    ]);
    $auditCount = AuditLog::query()->count();

    $report = app(BuildFinancialReport::class)->handle(
        $accountant,
        financialReportingPeriod('2026-04-01', '2026-04-30'),
    );

    expect($report)->toBe([
        'period' => ['fromDate' => '2026-04-01', 'throughDate' => '2026-04-30', 'timezone' => 'UTC'],
        'currency' => 'KES',
        'overall' => [
            'billedAmountMinor' => 3_500_050,
            'paidAmountMinor' => 1_000_000,
            'outstandingAmountMinor' => 2_500_050,
            'billCount' => 2,
            'paidBillCount' => 1,
            'outstandingBillCount' => 1,
        ],
        'consultation' => [
            'billedAmountMinor' => 1_000_000,
            'paidAmountMinor' => 1_000_000,
            'outstandingAmountMinor' => 0,
            'billCount' => 1,
        ],
        'procedure' => [
            'billedAmountMinor' => 2_500_050,
            'paidAmountMinor' => 0,
            'outstandingAmountMinor' => 2_500_050,
            'billCount' => 1,
        ],
        'flow' => ['paymentCount' => 1, 'receiptCount' => 1, 'financialClearanceCount' => 1],
    ])->and(BillItem::query()->orderBy('id')->get()
        ->map(fn (BillItem $billItem): array => $billItem->getAttributes())
        ->all())->toBe($billItemSnapshots)
        ->and(AuditLog::query()->count())->toBe($auditCount);

    expect(json_encode($report, JSON_THROW_ON_ERROR))
        ->not->toContain('Private consultation name')
        ->not->toContain('Private procedure name')
        ->not->toContain('Private audit metadata')
        ->not->toContain('patient_number')
        ->not->toContain('visit_number');
});

it('uses each authoritative financial event timestamp and inclusive period boundaries', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $olderService = ServiceCatalogItem::factory()->create(['unit_price_minor' => 125_050]);
    $periodService = ServiceCatalogItem::factory()->create(['unit_price_minor' => 240_000]);

    $this->travelTo('2025-12-31 23:59:59');
    $olderBill = Bill::factory()->for(Visit::factory())->create();
    BillItem::factory()->for($olderBill)->for($olderService)->create();
    $this->travelTo('2026-01-01 00:00:00');
    $olderPayment = Payment::factory()->for($olderBill)->for($accountant, 'recordedBy')->create();
    Receipt::factory()->for($olderPayment)->create();
    $olderBill->status = BillStatus::Paid;
    $olderBill->save();

    $this->travelTo('2026-01-31 23:59:59');
    $periodBill = Bill::factory()->for(Visit::factory())->create();
    BillItem::factory()->for($periodBill)->for($periodService)->create();
    $this->travelTo('2026-02-01 00:00:00');
    $periodPayment = Payment::factory()->for($periodBill)->for($accountant, 'recordedBy')->create();
    Receipt::factory()->for($periodPayment)->create();
    $periodBill->status = BillStatus::Paid;
    $periodBill->save();

    $report = app(BuildFinancialReport::class)->handle(
        $accountant,
        financialReportingPeriod('2026-01-01', '2026-01-31'),
    );

    expect($report['overall'])->toBe([
        'billedAmountMinor' => 240_000,
        'paidAmountMinor' => 125_050,
        'outstandingAmountMinor' => 0,
        'billCount' => 1,
        'paidBillCount' => 1,
        'outstandingBillCount' => 0,
    ])->and($report['flow'])->toBe([
        'paymentCount' => 1,
        'receiptCount' => 1,
        'financialClearanceCount' => 0,
    ]);
});

it('aggregates multiple immutable items without inflating payment or flow counts', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $firstService = ServiceCatalogItem::factory()->create(['unit_price_minor' => 10_000]);
    $secondService = ServiceCatalogItem::factory()->create(['unit_price_minor' => 20_000]);
    $this->travelTo('2026-05-15 10:00:00');
    $bill = Bill::factory()->for(Visit::factory())->create();
    BillItem::factory()->for($bill)->for($firstService)->create();
    BillItem::factory()->for($bill)->for($secondService)->create();
    $payment = Payment::factory()->for($bill)->for($accountant, 'recordedBy')->create();
    Receipt::factory()->for($payment)->create();
    $bill->status = BillStatus::Paid;
    $bill->save();
    FinancialClearance::factory()->for($bill)->for($accountant, 'grantedBy')->create();

    $report = app(BuildFinancialReport::class)->handle(
        $accountant,
        financialReportingPeriod('2026-05-01', '2026-05-31'),
    );

    expect($report['overall'])->toBe([
        'billedAmountMinor' => 30_000,
        'paidAmountMinor' => 30_000,
        'outstandingAmountMinor' => 0,
        'billCount' => 1,
        'paidBillCount' => 1,
        'outstandingBillCount' => 0,
    ])->and($report['flow'])->toBe([
        'paymentCount' => 1,
        'receiptCount' => 1,
        'financialClearanceCount' => 1,
    ]);
});

it('allows only the three active financial reporting roles at the query boundary', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();

    expect(app(BuildFinancialReport::class)->handle(
        $actor,
        financialReportingPeriod('2026-01-01', '2026-01-31'),
    )['currency'])->toBe('KES');
})->with([StaffRole::Accountant, StaffRole::Administrator, StaffRole::Management]);

it('denies operational roles and inactive financial reporting users at the query boundary', function (StaffRole $role, bool $active) {
    $actor = User::factory()->forRole($role)->state(['is_active' => $active])->create();

    expect(fn () => app(BuildFinancialReport::class)->handle(
        $actor,
        financialReportingPeriod('2026-01-01', '2026-01-31'),
    ))->toThrow(AuthorizationException::class);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Doctor' => [StaffRole::Doctor, true],
    'Nurse' => [StaffRole::Nurse, true],
    'inactive Accountant' => [StaffRole::Accountant, false],
    'inactive Administrator' => [StaffRole::Administrator, false],
    'inactive Management' => [StaffRole::Management, false],
]);

function financialReportingPeriod(string $fromDate, string $throughDate): ReportingPeriod
{
    return new ReportingPeriod(
        CarbonImmutable::parse($fromDate, 'UTC'),
        CarbonImmutable::parse($throughDate, 'UTC'),
    );
}
