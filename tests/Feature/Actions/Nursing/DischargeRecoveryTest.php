<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Clinical\ResolveRecoveryEscalation;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Actions\Nursing\DischargeRecovery;
use App\Actions\Visits\CompleteVisit;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\User;
use App\RecoveryDischargeAccompanimentStatus;
use App\RecoveryDischargeDisposition;
use App\RecoveryEpisodeStatus;
use App\RecoveryEscalationResolution;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('atomically finalizes a documented discharge by the responsible Nurse', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeAction();
    $this->travelTo('2026-09-11 09:30:00');

    $discharge = app(DischargeRecovery::class)->handle($nurse, $recovery, dischargeActionAttributes());
    $audit = AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->sole();
    $completionAudit = AuditLog::query()->where('action', AuditAction::VisitCompleted)->sole();
    $encodedAudit = json_encode($audit->toArray());
    $encodedCompletionAudit = json_encode($completionAudit->toArray());

    expect($discharge->recoveryEpisode->is($recovery))->toBeTrue()
        ->and($discharge->dischargedBy->is($nurse))->toBeTrue()
        ->and($discharge->discharge_number)->toBe(sprintf('DSC-%06d', $discharge->id))
        ->and($discharge->discharged_at->toDateTimeString())->toBe('2026-09-11 09:30:00')
        ->and($discharge->accompaniment_status)->toBe(RecoveryDischargeAccompanimentStatus::Accompanied)
        ->and($discharge->disposition)->toBe(RecoveryDischargeDisposition::Home)
        ->and($discharge->condition_summary)->toBe('Alert and comfortable at discharge.')
        ->and($discharge->general_care_instructions)->toBe('Rest for the remainder of today.')
        ->and($discharge->activity_driving_instructions)->toBe('Do not drive for the advised period.')
        ->and($discharge->diet_fluids_instructions)->toBe('Take fluids and resume diet as advised.')
        ->and($discharge->medication_instructions)->toBe('Continue usual medicines as instructed.')
        ->and($discharge->warning_signs_instructions)->toBe('Seek urgent care for severe pain or bleeding.')
        ->and($discharge->follow_up_instructions)->toBe('Attend the planned clinic review.')
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::Completed)
        ->and($recovery->fresh()->completed_at?->toDateTimeString())->toBe('2026-09-11 09:30:00')
        ->and($recovery->visit->fresh()->status)->toBe(VisitStatus::Completed)
        ->and($recovery->visit->fresh()->completed_at?->toDateTimeString())->toBe('2026-09-11 09:30:00')
        ->and($recovery->visit->fresh()->workflowMessage())->toBe('Discharged / Completed')
        ->and($audit->actor_id)->toBe($nurse->id)
        ->and($audit->subject->is($discharge))->toBeTrue()
        ->and($audit->after_values)->toHaveCount(8)
        ->and($audit->after_values)->toMatchArray([
            'discharge_number' => $discharge->discharge_number,
            'recovery_episode_id' => $recovery->id,
            'visit_id' => $recovery->visit_id,
            'discharged_by_user_id' => $nurse->id,
            'recovery_status' => RecoveryEpisodeStatus::Completed->value,
        ])
        ->and($completionAudit->actor_id)->toBe($nurse->id)
        ->and($completionAudit->subject->is($recovery->visit))->toBeTrue()
        ->and($completionAudit->after_values)->toHaveCount(7)
        ->and($completionAudit->after_values)->toMatchArray([
            'visit_id' => $recovery->visit_id,
            'visit_number' => $recovery->visit->visit_number,
            'completion_source_type' => 'recovery_discharge',
            'completion_source_id' => $discharge->id,
            'completion_source_reference' => $discharge->discharge_number,
        ])
        ->and($encodedAudit)->not->toContain('Alert and comfortable')
        ->and($encodedAudit)->not->toContain('usual medicines')
        ->and($encodedAudit)->not->toContain('severe pain')
        ->and($encodedCompletionAudit)->not->toContain('Alert and comfortable')
        ->and($encodedCompletionAudit)->not->toContain('usual medicines')
        ->and($encodedCompletionAudit)->not->toContain('severe pain');
});

it('rejects discharge before readiness or when the current assessment does not permit it', function () {
    [$recovery, $nurse] = recoveryForDischargeAction();

    expect(fn () => app(DischargeRecovery::class)->handle($nurse, $recovery, dischargeActionAttributes()))
        ->toThrow(AuthorizationException::class);

    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => false,
    ]);
    DB::table('recovery_episodes')->where('id', $recovery->id)->update([
        'status' => RecoveryEpisodeStatus::ReadyForDischarge->value,
    ]);

    expect(fn () => app(DischargeRecovery::class)->handle($nurse, $recovery->refresh(), dischargeActionAttributes()))
        ->toThrow(ValidationException::class, 'does not permit discharge');

    expect(RecoveryDischarge::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->count())->toBe(0);
});

it('blocks discharge while a Doctor-review escalation remains unresolved', function () {
    [$recovery, $nurse] = recoveryForDischargeAction();
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => true,
        'escalation_reason' => 'Clinical review remains required.',
    ]);
    DB::table('recovery_episodes')->where('id', $recovery->id)->update([
        'status' => RecoveryEpisodeStatus::ReadyForDischarge->value,
    ]);

    expect(fn () => app(DischargeRecovery::class)->handle($nurse, $recovery->refresh(), dischargeActionAttributes()))
        ->toThrow(AuthorizationException::class);

    expect(RecoveryDischarge::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->count())->toBe(0);
});

it('does not discharge merely because a Doctor resolves an escalation', function () {
    [$recovery, $nurse] = recoveryForDischargeAction();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => true,
        'escalation_reason' => 'Review required.',
    ]);

    app(ResolveRecoveryEscalation::class)->handle(
        $doctor,
        RecoveryEscalation::query()->sole(),
        ['resolution' => RecoveryEscalationResolution::ClinicallyCleared->value],
    );

    expect($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::InProgress)
        ->and(RecoveryDischarge::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->count())->toBe(0);
});

it('denies non-owner Nurses inactive owners and every non-Nurse role', function () {
    [$recovery, $owner] = readyRecoveryForDischargeAction();
    $actors = [
        User::factory()->forRole(StaffRole::Nurse)->create(),
        User::factory()->forRole(StaffRole::Receptionist)->create(),
        User::factory()->forRole(StaffRole::Accountant)->create(),
        User::factory()->forRole(StaffRole::Doctor)->create(),
        User::factory()->forRole(StaffRole::Administrator)->create(),
        User::factory()->forRole(StaffRole::Management)->create(),
    ];

    foreach ($actors as $actor) {
        expect(fn () => app(DischargeRecovery::class)->handle($actor, $recovery, dischargeActionAttributes()))
            ->toThrow(AuthorizationException::class);
    }

    User::query()->whereKey($owner->getKey())->update(['is_active' => false]);

    expect(fn () => app(DischargeRecovery::class)->handle($owner, $recovery, dischargeActionAttributes()))
        ->toThrow(ValidationException::class, 'responsible active Nurse');

    expect(RecoveryDischarge::query()->count())->toBe(0);
});

it('rejects duplicate discharge and creates no duplicate audit', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeAction();
    app(DischargeRecovery::class)->handle($nurse, $recovery, dischargeActionAttributes());

    expect(fn () => app(DischargeRecovery::class)->handle($nurse, $recovery->refresh(), dischargeActionAttributes()))
        ->toThrow(AuthorizationException::class);

    expect(RecoveryDischarge::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->count())->toBe(1);
});

it('rejects forged server-controlled discharge fields', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeAction();

    expect(fn () => app(DischargeRecovery::class)->handle($nurse, $recovery, dischargeActionAttributes([
        'recovery_episode_id' => 999,
        'discharged_by_user_id' => 999,
        'discharge_number' => 'DSC-FORGED',
        'discharged_at' => '2020-01-01 00:00:00',
        'status' => 'completed',
        'completed_at' => '2020-01-01 00:00:00',
    ])))->toThrow(ValidationException::class);

    expect(RecoveryDischarge::query()->count())->toBe(0)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::ReadyForDischarge);
});

it('rolls back the record recovery transition and audit when finalization fails', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeAction();
    $auditRecorder = Mockery::mock(RecordAuditLog::class);
    $auditRecorder->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit failed.'));

    expect(fn () => (new DischargeRecovery($auditRecorder, app(CompleteVisit::class)))->handle(
        $nurse,
        $recovery,
        dischargeActionAttributes(),
    ))->toThrow(RuntimeException::class, 'Audit failed.');

    expect(RecoveryDischarge::query()->count())->toBe(0)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::ReadyForDischarge)
        ->and($recovery->fresh()->completed_at)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->count())->toBe(0);
});

it('rolls back discharge and lifecycle completion when the Visit completion audit fails', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeAction();
    $auditCount = AuditLog::query()->count();
    $auditRecorder = Mockery::mock(RecordAuditLog::class);
    $auditRecorder->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $actor, AuditAction $action): bool => $actor->is($nurse)
            && $action === AuditAction::RecoveryDischarged)
        ->andReturn(new AuditLog)
        ->ordered();
    $auditRecorder->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $actor, AuditAction $action): bool => $actor->is($nurse)
            && $action === AuditAction::VisitCompleted)
        ->andThrow(new RuntimeException('Visit completion audit failed.'))
        ->ordered();
    $completeVisit = new CompleteVisit($auditRecorder);

    expect(fn () => (new DischargeRecovery($auditRecorder, $completeVisit))->handle(
        $nurse,
        $recovery,
        dischargeActionAttributes(),
    ))->toThrow(RuntimeException::class, 'Visit completion audit failed.');

    expect(RecoveryDischarge::query()->count())->toBe(0)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::ReadyForDischarge)
        ->and($recovery->fresh()->completed_at)->toBeNull()
        ->and($recovery->visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($recovery->visit->fresh()->completed_at)->toBeNull()
        ->and($recovery->visit->consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($recovery->visit->consultation->fresh()->finalized_at)->toBeNull()
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('removes the completed procedure Visit from every staff operational queue', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeAction();
    $decision = $recovery->procedureRecord->procedureDecision;
    $doctor = $decision->doctor;
    ProcedureBillingHandoff::createFromProcedureDecision($decision);

    app(DischargeRecovery::class)->handle($nurse, $recovery, dischargeActionAttributes());

    $this->actingAs(User::factory()->forRole(StaffRole::Receptionist)->create())
        ->get(route('check-ins.index'))
        ->assertInertia(fn (Assert $page) => $page->where('visits.pagination.total', 0));

    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
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

    $this->actingAs($nurse)
        ->get(route('nursing.pre-procedure-readiness.index'))
        ->assertInertia(fn (Assert $page) => $page->where('preparations.pagination.total', 0));
    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('awaitingRecoveries.pagination.total', 0)
            ->where('activeRecoveries.pagination.total', 0));
});

/** @return array{0: RecoveryEpisode, 1: User} */
function recoveryForDischargeAction(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $preparation);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    return [RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedure, $nurse), $nurse];
}

/** @return array{0: RecoveryEpisode, 1: User} */
function readyRecoveryForDischargeAction(): array
{
    [$recovery, $nurse] = recoveryForDischargeAction();
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
    ]);

    return [$recovery->refresh(), $nurse];
}

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function dischargeActionAttributes(array $overrides = []): array
{
    return array_replace([
        'condition_summary' => 'Alert and comfortable at discharge.',
        'accompaniment_status' => RecoveryDischargeAccompanimentStatus::Accompanied->value,
        'disposition' => RecoveryDischargeDisposition::Home->value,
        'nursing_note' => 'Instructions understood by patient and escort.',
        'general_care_instructions' => 'Rest for the remainder of today.',
        'activity_driving_instructions' => 'Do not drive for the advised period.',
        'diet_fluids_instructions' => 'Take fluids and resume diet as advised.',
        'medication_instructions' => 'Continue usual medicines as instructed.',
        'warning_signs_instructions' => 'Seek urgent care for severe pain or bleeding.',
        'follow_up_instructions' => 'Attend the planned clinic review.',
        'confirm_discharge' => true,
    ], $overrides);
}
