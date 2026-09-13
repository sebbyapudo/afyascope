<?php

use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Reporting\BuildOperationalReport;
use App\Actions\Reporting\OperationalVisitStage;
use App\Actions\Reporting\ReportingPeriod;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

it('builds operational measures and mutually exclusive stages from authoritative records', function () {
    $this->travelTo('2026-09-12 10:00:00');
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $awaitingBillingVisit = Visit::factory()->create();
    $consultationInProgress = Consultation::factory()
        ->for($doctor, 'doctor')
        ->create([
            'presenting_complaint' => 'Private operational-report exclusion narrative.',
        ]);
    $procedureConsultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $procedureDecision = app(RecordProcedureDecision::class)->handle($doctor, $procedureConsultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => ServiceCatalogItem::factory()->procedure()->create()->id,
        'clinical_rationale' => 'Private procedure rationale.',
        'confirmed' => true,
    ]);
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($procedureDecision, $nurse);
    $procedureRecord = ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($procedureDecision, $readiness, $doctor);
    $recovery = RecoveryEpisode::factory()
        ->createAuthoritativeRecoveryFixture($procedureRecord, $nurse);
    RecoveryEscalation::factory()->createAuthoritativeEscalationFixture($recovery, $nurse);
    $noProcedureConsultation = Consultation::factory()->for($doctor, 'doctor')->create();
    app(RecordProcedureDecision::class)->handle($doctor, $noProcedureConsultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'clinical_rationale' => null,
        'confirmed' => true,
    ]);
    $visitSnapshots = Visit::query()
        ->orderBy('id')
        ->get()
        ->map(fn (Visit $visit): array => $visit->getAttributes())
        ->all();
    $auditCount = AuditLog::query()->count();

    $report = app(BuildOperationalReport::class)->handle(
        $management,
        operationalReportingPeriod('2026-09-01', '2026-09-30'),
    );

    expect($report['metrics'])->toBe([
        'visits' => [
            'occurred' => 4,
            'active' => 3,
            'completed' => 1,
        ],
        'milestones' => [
            'consultationsStarted' => 3,
            'procedureRequired' => 1,
            'noProcedure' => 1,
            'proceduresCompleted' => 1,
            'recoveriesStarted' => 1,
            'dischargesCompleted' => 0,
        ],
    ]);

    $stageCounts = collect($report['stages'])->pluck('count', 'key');

    expect($stageCounts[OperationalVisitStage::AwaitingConsultationBilling->value])->toBe(1)
        ->and($stageCounts[OperationalVisitStage::ConsultationInProgress->value])->toBe(1)
        ->and($stageCounts[OperationalVisitStage::DoctorReviewRequired->value])->toBe(1)
        ->and($stageCounts[OperationalVisitStage::Completed->value])->toBe(1)
        ->and($stageCounts->sum())->toBe(4)
        ->and($awaitingBillingVisit->fresh()->status->value)->toBe('created')
        ->and($consultationInProgress->fresh()->status->value)->toBe('in_progress')
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(Visit::query()->orderBy('id')->get()->map(fn (Visit $visit): array => $visit->getAttributes())->all())
        ->toBe($visitSnapshots);

    $encodedReport = json_encode($report, JSON_THROW_ON_ERROR);

    expect($encodedReport)
        ->not->toContain('patient_number')
        ->not->toContain('Private operational-report exclusion narrative')
        ->not->toContain('Private procedure rationale')
        ->not->toContain('amount_minor')
        ->not->toContain('audit');
});

it('uses inclusive deterministic date boundaries for the Visit occurrence cohort', function () {
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $this->travelTo('2026-06-01 00:00:00');
    Visit::factory()->create();
    $this->travelTo('2026-06-30 23:59:59');
    Visit::factory()->create();
    $this->travelTo('2026-07-01 00:00:00');
    Visit::factory()->create();

    $report = app(BuildOperationalReport::class)->handle(
        $management,
        operationalReportingPeriod('2026-06-01', '2026-06-30'),
    );

    expect($report['period'])->toBe([
        'fromDate' => '2026-06-01',
        'throughDate' => '2026-06-30',
        'timezone' => 'UTC',
    ])->and($report['metrics']['visits']['occurred'])->toBe(2)
        ->and(collect($report['stages'])->sum('count'))->toBe(2);
});

it('allows only active Receptionist Administrator and Management users at the query boundary', function (StaffRole $role, bool $allowed) {
    $actor = User::factory()->forRole($role)->create();

    $operation = fn (): array => app(BuildOperationalReport::class)->handle(
        $actor,
        operationalReportingPeriod('2026-09-01', '2026-09-30'),
    );

    if ($allowed) {
        expect($operation())->toHaveKeys(['period', 'metrics', 'stages']);

        return;
    }

    expect($operation)->toThrow(AuthorizationException::class);
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, false],
    'Administrator' => [StaffRole::Administrator, true],
    'Management' => [StaffRole::Management, true],
]);

it('denies inactive operational-report users at the query boundary', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->inactive()->create();

    expect(fn () => app(BuildOperationalReport::class)->handle(
        $actor,
        operationalReportingPeriod('2026-09-01', '2026-09-30'),
    ))->toThrow(AuthorizationException::class);
})->with([
    StaffRole::Receptionist,
    StaffRole::Administrator,
    StaffRole::Management,
]);

function operationalReportingPeriod(string $fromDate, string $throughDate): ReportingPeriod
{
    return new ReportingPeriod(
        CarbonImmutable::parse($fromDate, 'UTC'),
        CarbonImmutable::parse($throughDate, 'UTC'),
    );
}
