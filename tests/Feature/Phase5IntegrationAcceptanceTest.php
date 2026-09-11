<?php

use App\Actions\Clinical\ResolveRecoveryEscalation;
use App\Actions\Consultations\BeginConsultation;
use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Actions\Nursing\DischargeRecovery;
use App\Actions\Nursing\RecordRecoveryObservation;
use App\Actions\Nursing\StartRecoveryEpisode;
use App\AuditAction;
use App\BillType;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\Receipt;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\RecoveryObservation;
use App\Models\RecoveryReadinessAssessment;
use App\Models\User;
use App\Models\VisitCheckIn;
use App\ProcedureDecisionOutcome;
use App\RecoveryDischargeAccompanimentStatus;
use App\RecoveryDischargeDisposition;
use App\RecoveryEpisodeStatus;
use App\RecoveryEscalationResolution;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('accepts uncomplicated recovery through Nurse discharge and terminal queue cleanup', function () {
    [$procedure, $nurse] = phase5AcceptanceCompletedProcedureContext();
    $visit = $procedure->visit;
    $consultation = $procedure->procedureDecision->consultation;
    $doctor = $procedure->doctor;
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $workflowMessages = [$visit->fresh()->workflowMessage()];

    $recovery = app(StartRecoveryEpisode::class)->handle($nurse, $procedure);
    $workflowMessages[] = $visit->fresh()->workflowMessage();
    $observation = app(RecordRecoveryObservation::class)->handle(
        $nurse,
        $recovery,
        phase5AcceptanceObservationAttributes(),
    );
    $assessment = app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
        'assessment_note' => 'Sensitive uncomplicated readiness note.',
    ]);
    $workflowMessages[] = $visit->fresh()->workflowMessage();
    $discharge = app(DischargeRecovery::class)->handle(
        $nurse,
        $recovery->refresh(),
        phase5AcceptanceDischargeAttributes(),
    );
    $workflowMessages[] = $visit->fresh()->workflowMessage();

    expect($workflowMessages)->toBe([
        'Ready for Nursing recovery',
        'Recovery in progress',
        'Ready for discharge',
        'Discharged / Completed',
    ]);

    expect($observation->recorded_by_user_id)->toBe($nurse->id)
        ->and($assessment->assessed_by_user_id)->toBe($nurse->id)
        ->and($discharge->discharged_by_user_id)->toBe($nurse->id)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::Completed)
        ->and($recovery->fresh()->completed_at?->equalTo($discharge->discharged_at))->toBeTrue()
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::Finalized)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Completed)
        ->and($visit->fresh()->completed_at?->equalTo($discharge->discharged_at))->toBeTrue()
        ->and(RecoveryEscalation::query()->count())->toBe(0)
        ->and(Route::has('visits.complete'))->toBeFalse()
        ->and(Route::has('nursing.recovery.complete'))->toBeFalse();

    foreach ([
        AuditAction::RecoveryStarted,
        AuditAction::RecoveryObservationRecorded,
        AuditAction::RecoveryReadinessAssessed,
        AuditAction::RecoveryDischarged,
        AuditAction::VisitCompleted,
    ] as $auditAction) {
        expect(AuditLog::query()->where('action', $auditAction)->count())->toBe(1);
    }

    expect(AuditLog::query()->whereIn('action', [
        AuditAction::RecoveryEscalated,
        AuditAction::RecoveryEscalationResolved,
    ])->count())->toBe(0);

    $phase5Audits = AuditLog::query()->get();
    $encodedAudits = json_encode($phase5Audits->toArray());

    expect($phase5Audits->every(fn (AuditLog $audit): bool => $audit->actor_id === $nurse->id))->toBeTrue()
        ->and($encodedAudits)
        ->not->toContain('Sensitive recovery status')
        ->not->toContain('Sensitive Nursing observation')
        ->not->toContain('Sensitive uncomplicated readiness note')
        ->not->toContain('Sensitive discharge condition')
        ->not->toContain('Sensitive medication instructions');

    $this->actingAs($receptionist)
        ->get(route('visits.index', ['q' => $visit->visit_number]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('visits.data', 1)
            ->where('visits.data.0.status.value', VisitStatus::Completed->value)
            ->where('visits.data.0.nextStep', 'Discharged / Completed'));
    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.nextStep', 'Discharged / Completed')
            ->where('visit.clinicalActors.doctor.name', $doctor->name)
            ->where('visit.clinicalActors.nurse.name', $nurse->name));
    $this->actingAs($receptionist)
        ->get(route('patients.show', $visit->patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.visitNumber', $visit->visit_number)
            ->where('visitHistory.data.0.nextStep', 'Discharged / Completed')
            ->where('visitHistory.data.0.discharge.dischargeNumber', $discharge->discharge_number));
    $this->actingAs($nurse)
        ->get(route('patient-activity.index', ['q' => $visit->visit_number]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.currentStage', 'Discharged / Completed')
            ->where('activities.data.0.activity.label', 'Visit completed')
            ->missing('activities.data.0.conditionSummary')
            ->missing('activities.data.0.observations'));
    $this->actingAs($doctor)
        ->get(route('clinical.procedures.show', $procedure))
        ->assertInertia(fn (Assert $page) => $page
            ->where('procedure.visit.nextStep', 'Discharged / Completed'));
    $this->actingAs($nurse)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.status.value', RecoveryEpisodeStatus::Completed->value)
            ->where('recovery.visit.nextStep', 'Discharged / Completed')
            ->where('recovery.canManage', false)
            ->where('recovery.canDischarge', false)
            ->where('recovery.discharge.dischargeNumber', $discharge->discharge_number));

    $this->actingAs($receptionist)
        ->get(route('check-ins.index'))
        ->assertInertia(fn (Assert $page) => $page->where('visits.pagination.total', 0));
    $this->actingAs($accountant)
        ->get(route('billing.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('visits.pagination.total', 0));
    $this->actingAs($accountant)
        ->get(route('billing.procedures.index'))
        ->assertInertia(fn (Assert $page) => $page->where('handoffs.pagination.total', 0));
    $this->actingAs($accountant)
        ->get(route('billing.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->where('bills.pagination.total', 0));
    $this->actingAs($accountant)
        ->get(route('billing.clearances.index'))
        ->assertInertia(fn (Assert $page) => $page->where('bills.pagination.total', 0));
    $this->actingAs($doctor)
        ->get(route('clinical.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('readyVisits.pagination.total', 0)
            ->where('inProgressConsultations.pagination.total', 0));
    $this->actingAs($doctor)
        ->get(route('clinical.procedures.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('readyProcedures.pagination.total', 0)
            ->where('inProgressProcedures.pagination.total', 0));
    $this->actingAs($doctor)
        ->get(route('clinical.recovery-escalations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('escalations', 0));
    $this->actingAs($nurse)
        ->get(route('nursing.pre-procedure-readiness.index'))
        ->assertInertia(fn (Assert $page) => $page->where('preparations.pagination.total', 0));
    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('awaitingRecoveries.pagination.total', 0)
            ->where('activeRecoveries.pagination.total', 0));
});

it('accepts escalated recovery only after Doctor resolution and Nurse reassessment', function () {
    [$procedure, $nurse] = phase5AcceptanceCompletedProcedureContext();
    $doctor = $procedure->doctor;
    $visit = $procedure->visit;
    $recovery = app(StartRecoveryEpisode::class)->handle($nurse, $procedure);

    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => true,
        'assessment_note' => 'Sensitive readiness assessment.',
        'escalation_reason' => 'Sensitive clinical concern requiring review.',
    ]);
    $escalation = RecoveryEscalation::query()->sole();

    expect($visit->fresh()->workflowMessage())->toBe('Doctor review required')
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::InProgress)
        ->and($escalation->open_marker)->toBeTrue();
    expect(fn () => app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
    ]))->toThrow(AuthorizationException::class);
    expect(fn () => app(DischargeRecovery::class)->handle(
        $nurse,
        $recovery,
        phase5AcceptanceDischargeAttributes(),
    ))->toThrow(AuthorizationException::class);
    expect(fn () => app(DischargeRecovery::class)->handle(
        $doctor,
        $recovery,
        phase5AcceptanceDischargeAttributes(),
    ))->toThrow(AuthorizationException::class);

    $this->actingAs($doctor)
        ->get(route('clinical.recovery-escalations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('escalations', 1)
            ->where('escalations.0.id', $escalation->id));

    app(ResolveRecoveryEscalation::class)->handle($doctor, $escalation, [
        'resolution' => RecoveryEscalationResolution::ContinueMonitoring->value,
        'resolution_note' => 'Sensitive Doctor resolution note.',
    ]);

    expect($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Recovery in progress')
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::InProgress)
        ->and(RecoveryDischarge::query()->count())->toBe(0)
        ->and(RecoveryEscalation::query()->count())->toBe(1)
        ->and(RecoveryEscalation::query()->where('open_marker', true)->count())->toBe(0);

    $this->actingAs($doctor)
        ->get(route('clinical.recovery-escalations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('escalations', 0));

    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery->refresh(), [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
        'assessment_note' => 'Sensitive post-review readiness note.',
    ]);
    $discharge = app(DischargeRecovery::class)->handle(
        $nurse,
        $recovery->refresh(),
        phase5AcceptanceDischargeAttributes(),
    );

    expect($discharge->discharged_by_user_id)->toBe($nurse->id)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Completed)
        ->and($visit->fresh()->workflowMessage())->toBe('Discharged / Completed')
        ->and(RecoveryReadinessAssessment::query()->count())->toBe(1)
        ->and(RecoveryDischarge::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryReadinessAssessed)->count())->toBe(2)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryEscalated)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryEscalationResolved)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::VisitCompleted)->count())->toBe(1);

    $resolutionAudit = AuditLog::query()->where('action', AuditAction::RecoveryEscalationResolved)->sole();
    $encodedAudits = json_encode(AuditLog::query()->get()->toArray());

    expect($resolutionAudit->actor_id)->toBe($doctor->id)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->sole()->actor_id)->toBe($nurse->id)
        ->and($encodedAudits)
        ->not->toContain('Sensitive readiness assessment')
        ->not->toContain('Sensitive clinical concern requiring review')
        ->not->toContain('Sensitive Doctor resolution note')
        ->not->toContain('Sensitive post-review readiness note');
});

it('accepts no-procedure as a terminal Doctor handoff without downstream artifacts', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $visit = VisitCheckIn::factory()->create()->visit;
    $consultation = app(BeginConsultation::class)->handle($doctor, $visit);
    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'clinical_rationale' => 'Sensitive rationale for conservative management.',
        'confirmed' => true,
    ]);

    expect($decision->outcome)->toBe(ProcedureDecisionOutcome::NoProcedure)
        ->and($decision->doctor_user_id)->toBe($doctor->id)
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::Finalized)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Completed)
        ->and($visit->fresh()->workflowMessage())->toBe('Consultation completed / Completed')
        ->and(ProcedureBillingHandoff::query()->where('visit_id', $visit->id)->count())->toBe(0)
        ->and(Bill::query()->where('visit_id', $visit->id)->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(Payment::query()->whereHas('bill', fn ($query) => $query
            ->where('visit_id', $visit->id)
            ->where('type', BillType::Procedure->value))->count())->toBe(0)
        ->and(Receipt::query()->whereHas('payment.bill', fn ($query) => $query
            ->where('visit_id', $visit->id)
            ->where('type', BillType::Procedure->value))->count())->toBe(0)
        ->and(FinancialClearance::query()->whereHas('bill', fn ($query) => $query
            ->where('visit_id', $visit->id)
            ->where('type', BillType::Procedure->value))->count())->toBe(0)
        ->and(PreProcedureReadiness::query()->where('visit_id', $visit->id)->count())->toBe(0)
        ->and(ProcedureRecord::query()->where('visit_id', $visit->id)->count())->toBe(0)
        ->and(RecoveryEpisode::query()->where('visit_id', $visit->id)->count())->toBe(0)
        ->and(RecoveryObservation::query()->count())->toBe(0)
        ->and(RecoveryReadinessAssessment::query()->count())->toBe(0)
        ->and(RecoveryEscalation::query()->count())->toBe(0)
        ->and(RecoveryDischarge::query()->count())->toBe(0);

    expect(AuditLog::query()->where('action', AuditAction::ConsultationProcedureDecided)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::VisitCompleted)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::VisitCompleted)->sole()->actor_id)->toBe($doctor->id)
        ->and(json_encode(AuditLog::query()->get()->toArray()))
        ->not->toContain('Sensitive rationale for conservative management');

    $this->actingAs($receptionist)
        ->get(route('patients.show', $visit->patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.visitNumber', $visit->visit_number)
            ->where('visitHistory.data.0.nextStep', 'Consultation completed / Completed')
            ->where('visitHistory.data.0.outcome.value', ProcedureDecisionOutcome::NoProcedure->value)
            ->where('visitHistory.data.0.discharge', null));
    $this->actingAs($receptionist)
        ->get(route('visits.index', ['q' => $visit->visit_number]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('visits.data', 1)
            ->where('visits.data.0.nextStep', 'Consultation completed / Completed'));
    $this->actingAs($doctor)
        ->get(route('patient-activity.index', ['q' => $visit->visit_number]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.currentStage', 'Consultation completed / Completed')
            ->where('activities.data.0.activity.label', 'Visit completed'));
    $this->actingAs($receptionist)
        ->get(route('check-ins.index'))
        ->assertInertia(fn (Assert $page) => $page->where('visits.pagination.total', 0));
    $this->actingAs($doctor)
        ->get(route('clinical.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('readyVisits.pagination.total', 0)
            ->where('inProgressConsultations.pagination.total', 0));
    $this->actingAs($doctor)
        ->get(route('clinical.procedures.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('readyProcedures.pagination.total', 0)
            ->where('inProgressProcedures.pagination.total', 0));
    $this->actingAs($doctor)
        ->get(route('clinical.recovery-escalations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('escalations', 0));
    $this->actingAs($nurse)
        ->get(route('nursing.pre-procedure-readiness.index'))
        ->assertInertia(fn (Assert $page) => $page->where('preparations.pagination.total', 0));
    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('awaitingRecoveries.pagination.total', 0)
            ->where('activeRecoveries.pagination.total', 0));
});

/** @return array{0: ProcedureRecord, 1: User} */
function phase5AcceptanceCompletedProcedureContext(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($decision, $preparation);

    return [$procedure, User::factory()->forRole(StaffRole::Nurse)->create()];
}

/** @return array<string, mixed> */
function phase5AcceptanceObservationAttributes(): array
{
    return [
        'general_recovery_status' => 'Sensitive recovery status.',
        'pain_score' => 2,
        'nausea' => false,
        'vomiting' => false,
        'systolic_blood_pressure' => 118,
        'diastolic_blood_pressure' => 76,
        'pulse_rate' => 72,
        'respiratory_rate' => 16,
        'oxygen_saturation' => 98,
        'supplemental_oxygen' => false,
        'nursing_note' => 'Sensitive Nursing observation.',
    ];
}

/** @return array<string, mixed> */
function phase5AcceptanceDischargeAttributes(): array
{
    return [
        'condition_summary' => 'Sensitive discharge condition.',
        'accompaniment_status' => RecoveryDischargeAccompanimentStatus::Accompanied->value,
        'disposition' => RecoveryDischargeDisposition::Home->value,
        'nursing_note' => 'Sensitive final Nursing note.',
        'general_care_instructions' => 'Sensitive general care instructions.',
        'activity_driving_instructions' => 'Sensitive activity instructions.',
        'diet_fluids_instructions' => 'Sensitive diet instructions.',
        'medication_instructions' => 'Sensitive medication instructions.',
        'warning_signs_instructions' => 'Sensitive warning signs.',
        'follow_up_instructions' => 'Sensitive follow-up instructions.',
        'confirm_discharge' => true,
    ];
}
