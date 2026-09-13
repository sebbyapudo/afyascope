<?php

use App\Actions\Administration\SetServiceCatalogItemActiveState;
use App\Actions\Administration\UpdateServiceCatalogItem;
use App\Actions\Administration\UpdateServiceCatalogItemPrice;
use App\Actions\Billing\CreateConsultationBill;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantConsultationFinancialClearance;
use App\Actions\Billing\RecordConsultationPayment;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Actions\Reporting\BuildClinicalProcedureReport;
use App\Actions\Reporting\BuildFinancialReport;
use App\Actions\Reporting\BuildManagementSummary;
use App\Actions\Reporting\BuildOperationalReport;
use App\Actions\Reporting\OperationalVisitStage;
use App\Actions\Reporting\ReportingPeriod;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\Receipt;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\RecoveryObservation;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\PaymentMethod;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

it('accepts the complete backend and frontend reporting access matrix', function (
    StaffRole $role,
    array $expectedAccess,
) {
    $user = User::factory()->forRole($role)->create();
    $reportRoutes = [
        'operational' => 'reports.operational.index',
        'financial' => 'reports.financial.index',
        'clinical' => 'reports.clinical.index',
        'management' => 'reports.management.index',
    ];

    foreach ($reportRoutes as $report => $routeName) {
        $response = $this->actingAs($user)->get(route($routeName));

        if ($expectedAccess[$report]) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }

    $capabilities = $this->actingAs($user)
        ->get(route('dashboard'))
        ->inertiaProps('auth.capabilities');

    expect($capabilities)->toBeArray();

    expect([
        'operational' => $capabilities['viewOperationalReports'],
        'financial' => $capabilities['viewFinancialReports'],
        'clinical' => $capabilities['viewClinicalReports'],
        'management' => $capabilities['viewManagementReports'],
    ])->toBe($expectedAccess);
})->with([
    'Receptionist' => [StaffRole::Receptionist, [
        'operational' => true,
        'financial' => false,
        'clinical' => false,
        'management' => false,
    ]],
    'Accountant' => [StaffRole::Accountant, [
        'operational' => false,
        'financial' => true,
        'clinical' => false,
        'management' => false,
    ]],
    'Doctor' => [StaffRole::Doctor, [
        'operational' => false,
        'financial' => false,
        'clinical' => true,
        'management' => false,
    ]],
    'Nurse' => [StaffRole::Nurse, [
        'operational' => false,
        'financial' => false,
        'clinical' => false,
        'management' => false,
    ]],
    'Administrator' => [StaffRole::Administrator, [
        'operational' => true,
        'financial' => true,
        'clinical' => true,
        'management' => true,
    ]],
    'Management' => [StaffRole::Management, [
        'operational' => true,
        'financial' => true,
        'clinical' => true,
        'management' => true,
    ]],
]);

it('redirects guests and inactive staff before any report is exposed', function () {
    $reportRoutes = [
        'reports.operational.index',
        'reports.financial.index',
        'reports.clinical.index',
        'reports.management.index',
    ];

    foreach ($reportRoutes as $routeName) {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    $inactiveRolesAndRoutes = [
        [StaffRole::Receptionist, 'reports.operational.index'],
        [StaffRole::Accountant, 'reports.financial.index'],
        [StaffRole::Doctor, 'reports.clinical.index'],
        [StaffRole::Nurse, 'reports.operational.index'],
        [StaffRole::Administrator, 'reports.management.index'],
        [StaffRole::Management, 'reports.management.index'],
    ];

    foreach ($inactiveRolesAndRoutes as [$role, $routeName]) {
        $user = User::factory()->forRole($role)->inactive()->create();

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertRedirect(route('login'));
    }
});

it('accepts consistent current custom and invalid periods on every report surface', function (
    string $routeName,
    string $rootProp,
    string $fromKey,
    string $toKey,
) {
    $this->travelTo('2026-09-12 10:00:00');
    $management = User::factory()->forRole(StaffRole::Management)->create();

    $this->actingAs($management)
        ->get(route($routeName))
        ->assertInertia(fn (Assert $page) => $page
            ->where("{$rootProp}.period.fromDate", '2026-09-01')
            ->where("{$rootProp}.period.throughDate", '2026-09-30')
            ->where("{$rootProp}.period.timezone", 'UTC'));

    $this->actingAs($management)
        ->get(route($routeName, [
            $fromKey => '2026-06-01',
            $toKey => '2026-06-30',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where("{$rootProp}.period.fromDate", '2026-06-01')
            ->where("{$rootProp}.period.throughDate", '2026-06-30'));

    $this->actingAs($management)
        ->from(route($routeName))
        ->get(route($routeName, [
            $fromKey => '2026-06-30',
            $toKey => '2026-06-01',
        ]))
        ->assertRedirect(route($routeName))
        ->assertSessionHasErrors([$toKey]);

    $this->actingAs($management)
        ->from(route($routeName))
        ->get(route($routeName, [$fromKey => '2026-06-01']))
        ->assertRedirect(route($routeName))
        ->assertSessionHasErrors([$toKey]);
})->with([
    'Operational Report' => ['reports.operational.index', 'report', 'date_from', 'date_to'],
    'Financial Report' => ['reports.financial.index', 'report', 'date_from', 'date_to'],
    'Clinical Report' => ['reports.clinical.index', 'report', 'date_from', 'date_to'],
    'Management Summary' => ['reports.management.index', 'summary', 'from', 'to'],
]);

it('includes records on both reporting boundaries without including the following day', function () {
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $this->travelTo('2026-06-01 00:00:00');
    Visit::factory()->create();
    $this->travelTo('2026-06-30 23:59:59');
    Visit::factory()->create();
    $this->travelTo('2026-07-01 00:00:00');
    Visit::factory()->create();
    $period = phase7AcceptancePeriod('2026-06-01', '2026-06-30');

    $operational = app(BuildOperationalReport::class)->handle($management, $period);
    $summary = app(BuildManagementSummary::class)->handle($management, $period);

    expect($operational['metrics']['visits']['occurred'])->toBe(2)
        ->and(collect($operational['stages'])->sum('count'))->toBe(2)
        ->and($summary['visits']['occurred'])->toBe(2);
});

it('accepts cross-report workflow facts management consistency privacy and read-only behavior', function () {
    $this->travelTo('2026-06-15 10:00:00');
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $service = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Original historical procedure',
        'unit_price_minor' => 250_000,
    ]);
    $activeConsultation = Consultation::factory()->for($doctor, 'doctor')->create([
        'presenting_complaint' => 'Private active consultation narrative.',
    ]);
    $noProcedureConsultation = Consultation::factory()->for($doctor, 'doctor')->create();
    app(RecordProcedureDecision::class)->handle($doctor, $noProcedureConsultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'clinical_rationale' => 'Private no-procedure rationale.',
        'confirmed' => true,
    ]);
    $awaitingProcedureConsultation = Consultation::factory()->for($doctor, 'doctor')->create();
    app(RecordProcedureDecision::class)->handle($doctor, $awaitingProcedureConsultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $service->id,
        'clinical_rationale' => 'Private awaiting-procedure rationale.',
        'confirmed' => true,
    ]);
    $completedProcedureConsultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $completedDecision = app(RecordProcedureDecision::class)->handle($doctor, $completedProcedureConsultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $service->id,
        'clinical_rationale' => 'Private completed-procedure rationale.',
        'confirmed' => true,
    ]);
    $preparation = PreProcedureReadiness::factory()
        ->ready()
        ->state(['observations' => 'Private Nursing preparation note.'])
        ->createAuthoritativePreparationFixture($completedDecision, $nurse);
    $procedure = ProcedureRecord::factory()
        ->completed()
        ->state([
            'findings' => 'Private procedure findings.',
            'procedure_notes' => 'Private procedure notes.',
        ])
        ->createAuthoritativeProcedureFixture($completedDecision, $preparation, $doctor);
    $recovery = RecoveryEpisode::factory()
        ->createAuthoritativeRecoveryFixture($procedure, $nurse);
    RecoveryObservation::factory()
        ->state([
            'general_recovery_status' => 'Private recovery observation.',
            'systolic_blood_pressure' => 118,
            'nursing_note' => 'Private recovery Nursing note.',
        ])
        ->createAuthoritativeObservationFixture($recovery, $nurse);
    RecoveryEscalation::factory()
        ->state(['reason' => 'Private escalation narrative.'])
        ->createResolvedEscalationFixture($recovery, $doctor);
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
        'assessment_note' => 'Private readiness assessment.',
    ]);
    RecoveryDischarge::factory()
        ->state([
            'condition_summary' => 'Private discharge condition.',
            'general_care_instructions' => 'Private discharge instructions.',
        ])
        ->createAuthoritativeDischargeFixture($recovery->refresh(), $nurse);
    app(UpdateServiceCatalogItem::class)->handle($administrator, $service, [
        'name' => 'Current historical procedure label',
        'category' => BillType::Procedure->value,
    ]);
    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        375_000,
        250_000,
    );
    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $service, false);
    AuditLog::factory()->create([
        'metadata' => ['secret' => 'Private raw reporting audit metadata.'],
    ]);
    $period = phase7AcceptancePeriod('2026-06-01', '2026-06-30');
    $recordCounts = phase7AcceptanceRecordCounts();
    $visitSnapshots = Visit::query()->orderBy('id')->get()
        ->map(fn (Visit $visit): array => $visit->getAttributes())
        ->all();
    $auditCount = AuditLog::query()->count();

    $operational = app(BuildOperationalReport::class)->handle($management, $period);
    $financial = app(BuildFinancialReport::class)->handle($management, $period);
    $clinical = app(BuildClinicalProcedureReport::class)->handle($management, $period);
    $summary = app(BuildManagementSummary::class)->handle($management, $period);

    expect($operational['metrics'])->toBe([
        'visits' => ['occurred' => 4, 'active' => 2, 'completed' => 2],
        'milestones' => [
            'consultationsStarted' => 4,
            'procedureRequired' => 2,
            'noProcedure' => 1,
            'proceduresCompleted' => 1,
            'recoveriesStarted' => 1,
            'dischargesCompleted' => 1,
        ],
    ]);

    $stageCounts = collect($operational['stages'])->pluck('count', 'key');
    expect($stageCounts->sum())->toBe(4)
        ->and($stageCounts[OperationalVisitStage::ConsultationInProgress->value])->toBe(1)
        ->and($stageCounts[OperationalVisitStage::AwaitingProcedureBilling->value])->toBe(1)
        ->and($stageCounts[OperationalVisitStage::Completed->value])->toBe(2)
        ->and($activeConsultation->fresh()->status->value)->toBe('in_progress');

    expect($clinical['consultationDecision'])->toBe([
        'consultationsStarted' => 4,
        'procedureRequired' => 2,
        'noProcedure' => 1,
    ])->and($clinical['procedure'])->toBe(['started' => 1, 'completed' => 1])
        ->and($clinical['preparation'])->toBe(['started' => 1, 'completed' => 1])
        ->and($clinical['recovery'])->toBe(['started' => 1, 'completed' => 1, 'discharged' => 1])
        ->and($clinical['escalation'])->toBe(['raised' => 1, 'resolved' => 1])
        ->and($clinical['terminalOutcomes'])->toBe([
            'procedurePathVisitsCompleted' => 1,
            'noProcedureVisitsCompleted' => 1,
        ])->and($clinical['procedureDistribution'])->toBe([[
            'procedureName' => 'Current historical procedure label',
            'procedureRequiredDecisions' => 2,
            'proceduresCompleted' => 1,
        ]]);

    expect($summary['visits'])->toBe($operational['metrics']['visits'])
        ->and($summary['financial']['billedAmountMinor'])->toBe($financial['overall']['billedAmountMinor'])
        ->and($summary['financial']['paidAmountMinor'])->toBe($financial['overall']['paidAmountMinor'])
        ->and($summary['financial']['outstandingAmountMinor'])->toBe($financial['overall']['outstandingAmountMinor'])
        ->and($summary['clinical']['proceduresCompleted'])->toBe($clinical['procedure']['completed'])
        ->and($summary['clinical']['recoveriesCompleted'])->toBe($clinical['recovery']['completed'])
        ->and($summary['clinical']['recoveryEscalationsRaised'])->toBe($clinical['escalation']['raised'])
        ->and($summary['clinical']['dischargesCompleted'])->toBe($clinical['recovery']['discharged']);

    $encodedReports = json_encode([$operational, $financial, $clinical, $summary], JSON_THROW_ON_ERROR);
    expect($encodedReports)
        ->not->toContain('Private active consultation narrative')
        ->not->toContain('Private no-procedure rationale')
        ->not->toContain('Private awaiting-procedure rationale')
        ->not->toContain('Private completed-procedure rationale')
        ->not->toContain('Private Nursing preparation note')
        ->not->toContain('Private procedure findings')
        ->not->toContain('Private procedure notes')
        ->not->toContain('Private recovery observation')
        ->not->toContain('Private recovery Nursing note')
        ->not->toContain('Private escalation narrative')
        ->not->toContain('Private readiness assessment')
        ->not->toContain('Private discharge condition')
        ->not->toContain('Private discharge instructions')
        ->not->toContain('Private raw reporting audit metadata')
        ->not->toContain('patient_number')
        ->not->toContain('visit_number')
        ->not->toContain('percentage')
        ->not->toContain('completionRate');

    expect(phase7AcceptanceRecordCounts())->toBe($recordCounts)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(Visit::query()->orderBy('id')->get()
            ->map(fn (Visit $visit): array => $visit->getAttributes())
            ->all())->toBe($visitSnapshots);
});

it('accepts event-time finance immutable snapshots and current catalog naming', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultationService = ServiceCatalogItem::factory()->create([
        'name' => 'Original consultation label',
        'unit_price_minor' => 100_000,
    ]);
    $procedureService = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Original procedure label',
        'unit_price_minor' => 250_000,
    ]);

    $this->travelTo('2026-03-31 23:59:59');
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $procedureService->id,
        'clinical_rationale' => null,
        'confirmed' => true,
    ]);
    $handoff = $decision->procedureBillingHandoff()->sole();

    $this->travelTo('2026-04-01 00:00:00');
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

    $this->travelTo('2026-04-15 10:00:00');
    $procedureBill = app(CreateProcedureBill::class)->handle($accountant, $handoff);

    $this->travelTo('2026-04-30 23:59:59');
    app(GrantConsultationFinancialClearance::class)->handle($accountant, $consultationBill);

    $this->travelTo('2026-05-01 00:00:00');
    app(RecordProcedurePayment::class)->handle($accountant, $procedureBill, PaymentMethod::Card);
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
    app(UpdateServiceCatalogItem::class)->handle($administrator, $procedureService, [
        'name' => 'Current renamed procedure label',
        'category' => BillType::Procedure->value,
    ]);
    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $procedureService, false);
    $billItemSnapshots = BillItem::query()->orderBy('id')->get()
        ->map(fn (BillItem $item): array => $item->getAttributes())
        ->all();
    $recordCounts = phase7AcceptanceRecordCounts();
    $auditCount = AuditLog::query()->count();

    $financial = app(BuildFinancialReport::class)->handle(
        $management,
        phase7AcceptancePeriod('2026-04-01', '2026-04-30'),
    );
    $clinical = app(BuildClinicalProcedureReport::class)->handle(
        $management,
        phase7AcceptancePeriod('2026-03-01', '2026-03-31'),
    );

    expect($financial['overall'])->toBe([
        'billedAmountMinor' => 350_000,
        'paidAmountMinor' => 100_000,
        'outstandingAmountMinor' => 0,
        'billCount' => 2,
        'paidBillCount' => 2,
        'outstandingBillCount' => 0,
    ])->and($financial['consultation'])->toBe([
        'billedAmountMinor' => 100_000,
        'paidAmountMinor' => 100_000,
        'outstandingAmountMinor' => 0,
        'billCount' => 1,
    ])->and($financial['procedure'])->toBe([
        'billedAmountMinor' => 250_000,
        'paidAmountMinor' => 0,
        'outstandingAmountMinor' => 0,
        'billCount' => 1,
    ])->and($financial['flow'])->toBe([
        'paymentCount' => 1,
        'receiptCount' => 1,
        'financialClearanceCount' => 1,
    ]);

    expect($clinical['procedureDistribution'])->toBe([[
        'procedureName' => 'Current renamed procedure label',
        'procedureRequiredDecisions' => 1,
        'proceduresCompleted' => 0,
    ]])->and($consultationBill->items->sole()->amount_minor)->toBe(100_000)
        ->and($procedureBill->items->sole()->amount_minor)->toBe(250_000)
        ->and($consultationService->fresh()->unit_price_minor)->toBe(175_000)
        ->and($procedureService->fresh()->unit_price_minor)->toBe(325_000)
        ->and($procedureService->fresh()->is_active)->toBeFalse()
        ->and(BillItem::query()->orderBy('id')->get()
            ->map(fn (BillItem $item): array => $item->getAttributes())
            ->all())->toBe($billItemSnapshots)
        ->and(phase7AcceptanceRecordCounts())->toBe($recordCounts)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('keeps actual report responses aggregate only and side effect free', function () {
    $this->travelTo('2026-08-15 10:00:00');
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $consultation = Consultation::factory()->create([
        'presenting_complaint' => 'NeverExposePhaseSevenNarrative',
    ]);
    $consultation->visit->patient->update([
        'first_name' => 'NeverExposePhaseSevenPatient',
        'last_name' => 'AggregateOnly',
    ]);
    AuditLog::factory()->create([
        'metadata' => ['secret' => 'NeverExposePhaseSevenAuditMetadata'],
    ]);
    $visitReference = $consultation->visit->visit_number;
    $patientReference = $consultation->visit->patient->patient_number;
    $recordCounts = phase7AcceptanceRecordCounts();
    $auditCount = AuditLog::query()->count();
    $requests = [
        ['reports.operational.index', 'report', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']],
        ['reports.financial.index', 'report', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']],
        ['reports.clinical.index', 'report', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']],
        ['reports.management.index', 'summary', ['from' => '2026-08-01', 'to' => '2026-08-31']],
    ];

    foreach ($requests as [$routeName, $rootProp, $query]) {
        $payload = $this->actingAs($management)
            ->get(route($routeName, $query))
            ->inertiaProps($rootProp);
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        expect($encodedPayload)
            ->not->toContain('NeverExposePhaseSevenNarrative')
            ->not->toContain('NeverExposePhaseSevenPatient')
            ->not->toContain('NeverExposePhaseSevenAuditMetadata')
            ->not->toContain($visitReference)
            ->not->toContain($patientReference)
            ->not->toContain('password')
            ->not->toContain('remember_token');
    }

    expect(phase7AcceptanceRecordCounts())->toBe($recordCounts)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

/** @return array<string, int> */
function phase7AcceptanceRecordCounts(): array
{
    return [
        'visits' => Visit::query()->count(),
        'bills' => Bill::query()->count(),
        'bill_items' => BillItem::query()->count(),
        'payments' => Payment::query()->count(),
        'receipts' => Receipt::query()->count(),
        'clearances' => FinancialClearance::query()->count(),
        'consultations' => Consultation::query()->count(),
        'decisions' => ProcedureDecision::query()->count(),
        'preparations' => PreProcedureReadiness::query()->count(),
        'procedures' => ProcedureRecord::query()->count(),
        'recoveries' => RecoveryEpisode::query()->count(),
        'recovery_observations' => RecoveryObservation::query()->count(),
        'escalations' => RecoveryEscalation::query()->count(),
        'discharges' => RecoveryDischarge::query()->count(),
        'catalog_items' => ServiceCatalogItem::query()->count(),
        'users' => User::query()->count(),
    ];
}

function phase7AcceptancePeriod(string $fromDate, string $throughDate): ReportingPeriod
{
    return new ReportingPeriod(
        CarbonImmutable::parse($fromDate, 'UTC'),
        CarbonImmutable::parse($throughDate, 'UTC'),
    );
}
