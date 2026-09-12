<?php

use App\Actions\Administration\UpdateServiceCatalogItemPrice;
use App\Actions\Billing\CreateConsultationBill;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\RecordConsultationPayment;
use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Actions\Reporting\BuildManagementSummary;
use App\Actions\Reporting\ReportingPeriod;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\PaymentMethod;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

it('normalizes an inclusive reporting period in the configured timezone', function () {
    $period = new ReportingPeriod(
        CarbonImmutable::parse('2026-06-01 15:30:00', 'UTC'),
        CarbonImmutable::parse('2026-06-30 08:15:00', 'UTC'),
    );

    expect($period->startsAt->toIso8601String())->toBe('2026-06-01T00:00:00+00:00')
        ->and($period->endsAt->toIso8601String())->toBe('2026-06-30T23:59:59+00:00')
        ->and($period->endsAt->micro)->toBe(999_999)
        ->and($period->bounds())->toBe([$period->startsAt, $period->endsAt]);

    expect(fn () => new ReportingPeriod(
        CarbonImmutable::parse('2026-07-01', 'UTC'),
        CarbonImmutable::parse('2026-06-30', 'UTC'),
    ))->toThrow(InvalidArgumentException::class);
});

it('derives operational and clinical measures from authoritative lifecycle records only', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $activeVisit = Visit::factory()->create();
    $noProcedureConsultation = Consultation::factory()
        ->for($doctor, 'doctor')
        ->create([
            'presenting_complaint' => 'Private clinical narrative must not be reported.',
        ]);

    app(RecordProcedureDecision::class)->handle($doctor, $noProcedureConsultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'clinical_rationale' => 'Private decision rationale must not be reported.',
        'confirmed' => true,
    ]);

    $procedureDecision = ProcedureDecision::factory()
        ->procedureRequired()
        ->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $preparation = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($procedureDecision, $nurse);
    $procedureRecord = ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($procedureDecision, $preparation);
    $recovery = RecoveryEpisode::factory()
        ->createAuthoritativeRecoveryFixture($procedureRecord, $nurse);

    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
        'assessment_note' => 'Private recovery assessment must not be reported.',
    ]);
    RecoveryDischarge::factory()
        ->state(['nursing_note' => 'Private discharge note must not be reported.'])
        ->createAuthoritativeDischargeFixture($recovery->refresh(), $nurse);
    AuditLog::factory()->create([
        'action' => AuditAction::ConsultationAssessmentUpdated,
        'metadata' => ['secret' => 'Private raw audit metadata must not be reported.'],
    ]);
    $this->travelTo('2026-07-01 00:00:00');
    Visit::factory()->create();

    $summary = app(BuildManagementSummary::class)->handle(
        $management,
        reportingPeriod('2026-06-01', '2026-06-30'),
    );

    expect($summary['visits'])->toBe([
        'occurred' => 3,
        'active' => 1,
        'completed' => 2,
    ])->and($summary['clinical'])->toBe([
        'consultationsStarted' => 2,
        'procedureRequired' => 1,
        'noProcedure' => 1,
        'proceduresCompleted' => 1,
        'recoveriesStarted' => 1,
        'dischargesCompleted' => 1,
    ])->and($activeVisit->fresh()->status->value)->toBe('created');

    $encodedSummary = json_encode($summary, JSON_THROW_ON_ERROR);

    expect($encodedSummary)
        ->not->toContain('Private clinical narrative')
        ->not->toContain('Private decision rationale')
        ->not->toContain('Private recovery assessment')
        ->not->toContain('Private discharge note')
        ->not->toContain('Private raw audit metadata')
        ->not->toContain('patient_number')
        ->not->toContain('audit_logs');
});

it('uses immutable Bill snapshots and payment records for date-bounded financial measures', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $consultationService = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => 100_000,
    ]);
    $procedureService = ServiceCatalogItem::factory()->procedure()->create([
        'unit_price_minor' => 250_000,
    ]);

    $this->travelTo('2025-12-31 23:59:59');
    $procedureHandoff = ProcedureBillingHandoff::factory()
        ->for($procedureService, 'serviceCatalogItem')
        ->createAuthoritativeDecisionFixture();

    $this->travelTo('2026-01-01 00:00:00');
    $consultationBill = app(CreateConsultationBill::class)->handle(
        $accountant,
        Visit::factory()->create(),
        $consultationService,
    );
    app(RecordConsultationPayment::class)->handle(
        $accountant,
        $consultationBill,
        PaymentMethod::Cash,
    );

    $this->travelTo('2026-01-31 23:59:59');
    $procedureBill = app(CreateProcedureBill::class)->handle($accountant, $procedureHandoff);

    $this->travelTo('2026-02-01 00:00:00');
    app(CreateConsultationBill::class)->handle(
        $accountant,
        Visit::factory()->create(),
        $consultationService,
    );
    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $consultationService,
        175_000,
        100_000,
    );
    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $procedureService,
        325_000,
        250_000,
    );

    $summary = app(BuildManagementSummary::class)->handle(
        $management,
        reportingPeriod('2026-01-01', '2026-01-31'),
    );

    expect($summary['financial'])->toBe([
        'billedAmountMinor' => 350_000,
        'paidAmountMinor' => 100_000,
        'outstandingAmountMinor' => 250_000,
        'consultation' => [
            'billedAmountMinor' => 100_000,
            'paidAmountMinor' => 100_000,
            'outstandingAmountMinor' => 0,
        ],
        'procedure' => [
            'billedAmountMinor' => 250_000,
            'paidAmountMinor' => 0,
            'outstandingAmountMinor' => 250_000,
        ],
    ])->and($consultationBill->fresh()->items->sole()->amount_minor)->toBe(100_000)
        ->and($procedureBill->fresh()->items->sole()->amount_minor)->toBe(250_000)
        ->and($consultationService->fresh()->unit_price_minor)->toBe(175_000)
        ->and($procedureService->fresh()->unit_price_minor)->toBe(325_000);

    foreach ($summary['financial'] as $measure) {
        if (is_array($measure)) {
            expect(array_values($measure))->each->toBeInt();
        } else {
            expect($measure)->toBeInt();
        }
    }
});

it('allows only active Management and Administrators at the reporting query boundary', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();

    $summary = app(BuildManagementSummary::class)->handle(
        $actor,
        reportingPeriod('2026-01-01', '2026-01-31'),
    );

    expect($summary['period'])->toBe([
        'fromDate' => '2026-01-01',
        'throughDate' => '2026-01-31',
        'timezone' => 'UTC',
    ]);
})->with([
    StaffRole::Management,
    StaffRole::Administrator,
]);

it('denies operational roles and inactive reporting users at the query boundary', function (StaffRole $role, bool $isActive) {
    $actor = User::factory()
        ->forRole($role)
        ->state(['is_active' => $isActive])
        ->create();

    expect(fn () => app(BuildManagementSummary::class)->handle(
        $actor,
        reportingPeriod('2026-01-01', '2026-01-31'),
    ))->toThrow(AuthorizationException::class);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, true],
    'Nurse' => [StaffRole::Nurse, true],
    'inactive Management' => [StaffRole::Management, false],
    'inactive Administrator' => [StaffRole::Administrator, false],
]);

function reportingPeriod(string $fromDate, string $throughDate): ReportingPeriod
{
    return new ReportingPeriod(
        CarbonImmutable::parse($fromDate, 'UTC'),
        CarbonImmutable::parse($throughDate, 'UTC'),
    );
}
