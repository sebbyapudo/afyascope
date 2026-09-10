<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Actions\Nursing\RecordRecoveryObservation;
use App\Actions\Nursing\StartRecoveryEpisode;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryObservation;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('records serial append-only observations for the responsible Nurse without changing workflow state', function () {
    $this->travelTo('2026-09-09 12:00:00');
    [$recovery, $nurse] = observationActionRecovery();

    $first = app(RecordRecoveryObservation::class)->handle($nurse, $recovery, observationAttributes());
    $this->travelTo('2026-09-09 12:15:00');
    $second = app(RecordRecoveryObservation::class)->handle($nurse, $recovery, observationAttributes([
        'general_recovery_status' => '  Awake and comfortable  ',
        'nursing_note' => '   ',
        'pain_score' => 2,
    ]));

    expect($first->recorded_at->toDateTimeString())->toBe('2026-09-09 12:00:00')
        ->and($second->recorded_at->toDateTimeString())->toBe('2026-09-09 12:15:00')
        ->and($second->general_recovery_status)->toBe('Awake and comfortable')
        ->and($second->nursing_note)->toBeNull()
        ->and($second->recorded_by_user_id)->toBe($nurse->id)
        ->and($second->recovery_episode_id)->toBe($recovery->id)
        ->and(RecoveryObservation::query()->count())->toBe(2)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::InProgress)
        ->and($recovery->visit->fresh()->workflowMessage())->toBe('Recovery in progress');

    $audits = AuditLog::query()->where('action', AuditAction::RecoveryObservationRecorded)->get();
    $encoded = json_encode($audits->toArray());

    expect($audits)->toHaveCount(2)
        ->and($audits->last()->subject_type)->toBe(RecoveryObservation::class)
        ->and($audits->last()->subject_id)->toBe($second->id)
        ->and($encoded)->not->toContain('Awake')
        ->and($encoded)->not->toContain('pain_score')
        ->and($encoded)->not->toContain('nursing_note');
});

it('rejects other roles other Nurses and inactive owners without observations or audits', function () {
    [$recovery, $owner] = observationActionRecovery();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    expect(fn () => app(RecordRecoveryObservation::class)->handle($otherNurse, $recovery, observationAttributes()))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(RecordRecoveryObservation::class)->handle($doctor, $recovery, observationAttributes()))
        ->toThrow(AuthorizationException::class);

    User::query()->whereKey($owner->getKey())->update(['is_active' => false]);
    expect(fn () => app(RecordRecoveryObservation::class)->handle($owner, $recovery, observationAttributes()))
        ->toThrow(ValidationException::class, 'responsible active Nurse');

    expect(RecoveryObservation::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryObservationRecorded)->count())->toBe(0);
});

it('rejects invalid forged context and rolls back if audit recording fails', function () {
    [$recovery, $nurse] = observationActionRecovery();

    expect(fn () => app(RecordRecoveryObservation::class)->handle($nurse, $recovery, observationAttributes([
        'pain_score' => 11,
        'recorded_at' => '2020-01-01 00:00:00',
    ])))->toThrow(ValidationException::class);

    $auditRecorder = Mockery::mock(RecordAuditLog::class);
    $auditRecorder->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit failed.'));

    expect(fn () => (new RecordRecoveryObservation($auditRecorder))->handle($nurse, $recovery, observationAttributes()))
        ->toThrow(RuntimeException::class, 'Audit failed.');

    expect(RecoveryObservation::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryObservationRecorded)->count())->toBe(0);
});

it('rejects a stale non-active recovery episode', function () {
    $procedure = observationActionCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recovery = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedure, $nurse);
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
    ]);
    RecoveryDischarge::factory()->createAuthoritativeDischargeFixture($recovery->refresh(), $nurse);
    $recovery->refresh();

    expect(fn () => app(RecordRecoveryObservation::class)->handle($nurse, $recovery, observationAttributes()))
        ->toThrow(AuthorizationException::class);

    expect(RecoveryObservation::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryObservationRecorded)->count())->toBe(0);
});

/** @return array{0: RecoveryEpisode, 1: User} */
function observationActionRecovery(): array
{
    $procedure = observationActionCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    return [app(StartRecoveryEpisode::class)->handle($nurse, $procedure), $nurse];
}

function observationActionCompletedProcedure(): ProcedureRecord
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );

    return ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $readiness);
}

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function observationAttributes(array $overrides = []): array
{
    return array_replace([
        'general_recovery_status' => 'Drowsy but responsive',
        'pain_score' => 3,
        'nausea' => false,
        'vomiting' => false,
        'systolic_blood_pressure' => 118,
        'diastolic_blood_pressure' => 76,
        'pulse_rate' => 74,
        'respiratory_rate' => 16,
        'oxygen_saturation' => 98,
        'supplemental_oxygen' => false,
        'nursing_note' => 'Monitoring continues.',
    ], $overrides);
}
