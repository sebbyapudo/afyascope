<?php

use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Actions\Reporting\BuildClinicalProcedureReport;
use App\Actions\Reporting\ReportingPeriod;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\ProcedureDecisionOutcome;
use App\RecoveryEscalationResolution;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

it('reports aggregate clinical workflow events without exposing clinical narratives or identifiers', function () {
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $service = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Original procedure label',
        'unit_price_minor' => 250_000,
    ]);

    $this->travelTo('2026-05-31 23:59:59');
    $procedureDecision = ProcedureDecision::factory()
        ->procedureRequired($service)
        ->state(['clinical_rationale' => 'Private procedure rationale'])
        ->createAuthoritativeDecisionFixture();

    $this->travelTo('2026-06-01 00:00:00');
    $preparation = PreProcedureReadiness::factory()
        ->ready()
        ->state(['observations' => 'Private preparation observations'])
        ->createAuthoritativePreparationFixture($procedureDecision, $nurse);
    $procedureRecord = ProcedureRecord::factory()
        ->completed()
        ->state([
            'findings' => 'Private procedure findings',
            'procedure_notes' => 'Private procedure notes',
        ])
        ->createAuthoritativeProcedureFixture($procedureDecision, $preparation);
    $recovery = RecoveryEpisode::factory()
        ->createAuthoritativeRecoveryFixture($procedureRecord, $nurse);

    $this->travelTo('2026-06-15 10:00:00');
    RecoveryEscalation::factory()
        ->state(['reason' => 'Private escalation reason'])
        ->createResolvedEscalationFixture($recovery, $doctor);

    $this->travelTo('2026-06-30 23:59:59');
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
        'assessment_note' => 'Private recovery assessment',
    ]);
    RecoveryDischarge::factory()
        ->state([
            'condition_summary' => 'Private discharge condition',
            'nursing_note' => 'Private discharge nursing note',
        ])
        ->createAuthoritativeDischargeFixture($recovery->refresh(), $nurse);

    $noProcedureConsultation = Consultation::factory()
        ->for($doctor, 'doctor')
        ->create(['presenting_complaint' => 'Private consultation narrative']);
    app(RecordProcedureDecision::class)->handle($doctor, $noProcedureConsultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'clinical_rationale' => 'Private no-procedure rationale',
        'confirmed' => true,
    ]);

    $service->update([
        'name' => 'Current procedure label',
        'unit_price_minor' => 375_000,
        'is_active' => false,
    ]);
    AuditLog::factory()->create([
        'action' => AuditAction::ConsultationAssessmentUpdated,
        'metadata' => ['secret' => 'Private raw audit metadata'],
    ]);
    $auditCount = AuditLog::query()->count();
    $workflowCounts = [
        Consultation::class => Consultation::query()->count(),
        ProcedureDecision::class => ProcedureDecision::query()->count(),
        ProcedureRecord::class => ProcedureRecord::query()->count(),
        PreProcedureReadiness::class => PreProcedureReadiness::query()->count(),
        RecoveryEpisode::class => RecoveryEpisode::query()->count(),
        RecoveryEscalation::class => RecoveryEscalation::query()->count(),
        RecoveryDischarge::class => RecoveryDischarge::query()->count(),
        Visit::class => Visit::query()->count(),
    ];

    $report = app(BuildClinicalProcedureReport::class)->handle(
        $management,
        clinicalReportingPeriod('2026-06-01', '2026-06-30'),
    );

    expect($report)->toBe([
        'period' => ['fromDate' => '2026-06-01', 'throughDate' => '2026-06-30', 'timezone' => 'UTC'],
        'consultationDecision' => [
            'consultationsStarted' => 1,
            'procedureRequired' => 0,
            'noProcedure' => 1,
        ],
        'procedure' => ['started' => 1, 'completed' => 1],
        'preparation' => ['started' => 1, 'completed' => 1],
        'recovery' => ['started' => 1, 'completed' => 1, 'discharged' => 1],
        'escalation' => ['raised' => 1, 'resolved' => 1],
        'terminalOutcomes' => [
            'procedurePathVisitsCompleted' => 1,
            'noProcedureVisitsCompleted' => 1,
        ],
        'procedureDistribution' => [[
            'procedureName' => 'Current procedure label',
            'procedureRequiredDecisions' => 0,
            'proceduresCompleted' => 1,
        ]],
    ])->and(AuditLog::query()->count())->toBe($auditCount)
        ->and($service->fresh()->unit_price_minor)->toBe(375_000)
        ->and($service->fresh()->is_active)->toBeFalse();

    foreach ($workflowCounts as $model => $count) {
        expect($model::query()->count())->toBe($count);
    }

    expect(json_encode($report, JSON_THROW_ON_ERROR))
        ->not->toContain('Private procedure rationale')
        ->not->toContain('Private preparation observations')
        ->not->toContain('Private procedure findings')
        ->not->toContain('Private procedure notes')
        ->not->toContain('Private escalation reason')
        ->not->toContain('Private recovery assessment')
        ->not->toContain('Private discharge condition')
        ->not->toContain('Private consultation narrative')
        ->not->toContain('Private raw audit metadata')
        ->not->toContain('patient_number')
        ->not->toContain('visit_number')
        ->not->toContain('amount_minor');
});

it('uses each authoritative lifecycle timestamp and inclusive period boundaries', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $service = ServiceCatalogItem::factory()->procedure()->create();

    $this->travelTo('2026-03-31 23:59:59');
    $decision = ProcedureDecision::factory()
        ->procedureRequired($service)
        ->createAuthoritativeDecisionFixture();

    $this->travelTo('2026-04-01 00:00:00');
    $preparation = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);
    $procedure = ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($decision, $preparation);
    $recovery = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedure, $nurse);

    $this->travelTo('2026-04-30 23:59:59');
    $escalation = RecoveryEscalation::factory()->createAuthoritativeEscalationFixture($recovery, $nurse);

    $this->travelTo('2026-05-01 00:00:00');
    $escalation->resolveFromClinicalWorkflow($doctor, RecoveryEscalationResolution::ContinueMonitoring, null);

    $report = app(BuildClinicalProcedureReport::class)->handle(
        $administrator,
        clinicalReportingPeriod('2026-04-01', '2026-04-30'),
    );

    expect($report['consultationDecision'])->toBe([
        'consultationsStarted' => 0,
        'procedureRequired' => 0,
        'noProcedure' => 0,
    ])->and($report['procedure'])->toBe(['started' => 1, 'completed' => 1])
        ->and($report['preparation'])->toBe(['started' => 1, 'completed' => 1])
        ->and($report['recovery'])->toBe(['started' => 1, 'completed' => 0, 'discharged' => 0])
        ->and($report['escalation'])->toBe(['raised' => 1, 'resolved' => 0])
        ->and($report['procedureDistribution'])->toBe([[
            'procedureName' => $service->name,
            'procedureRequiredDecisions' => 0,
            'proceduresCompleted' => 1,
        ]]);
});

it('aggregates each procedure decision and completion once by durable service relationship', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $service = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Initial catalog label',
        'unit_price_minor' => 175_000,
    ]);
    $this->travelTo('2026-07-15 10:00:00');
    $completedDecision = ProcedureDecision::factory()
        ->procedureRequired($service)
        ->createAuthoritativeDecisionFixture();
    ProcedureDecision::factory()
        ->procedureRequired($service)
        ->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($completedDecision, $nurse);
    ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($completedDecision, $preparation);

    $service->update([
        'name' => 'Renamed catalog label',
        'unit_price_minor' => 300_000,
        'is_active' => false,
    ]);

    $report = app(BuildClinicalProcedureReport::class)->handle(
        $doctor,
        clinicalReportingPeriod('2026-07-01', '2026-07-31'),
    );

    expect($report['consultationDecision']['procedureRequired'])->toBe(2)
        ->and($report['procedure']['completed'])->toBe(1)
        ->and($report['procedureDistribution'])->toBe([[
            'procedureName' => 'Renamed catalog label',
            'procedureRequiredDecisions' => 2,
            'proceduresCompleted' => 1,
        ]]);
});

it('allows only active Doctor Administrator and Management roles at the report query boundary', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();

    expect(app(BuildClinicalProcedureReport::class)->handle(
        $actor,
        clinicalReportingPeriod('2026-01-01', '2026-01-31'),
    )['period'])->toBe([
        'fromDate' => '2026-01-01',
        'throughDate' => '2026-01-31',
        'timezone' => 'UTC',
    ]);
})->with([StaffRole::Doctor, StaffRole::Administrator, StaffRole::Management]);

it('denies other roles and inactive clinical reporting users at the query boundary', function (StaffRole $role, bool $active) {
    $actor = User::factory()->forRole($role)->state(['is_active' => $active])->create();

    expect(fn () => app(BuildClinicalProcedureReport::class)->handle(
        $actor,
        clinicalReportingPeriod('2026-01-01', '2026-01-31'),
    ))->toThrow(AuthorizationException::class);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, true],
    'Nurse' => [StaffRole::Nurse, true],
    'inactive Doctor' => [StaffRole::Doctor, false],
    'inactive Administrator' => [StaffRole::Administrator, false],
    'inactive Management' => [StaffRole::Management, false],
]);

function clinicalReportingPeriod(string $fromDate, string $throughDate): ReportingPeriod
{
    return new ReportingPeriod(
        CarbonImmutable::parse($fromDate, 'UTC'),
        CarbonImmutable::parse($throughDate, 'UTC'),
    );
}
