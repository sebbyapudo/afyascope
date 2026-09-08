<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Nursing\StartRecoveryEpisode;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\ProcedureRecordStatus;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('starts one Nurse-owned recovery episode from the completed Procedure Record handoff', function () {
    $this->travelTo('2026-09-09 10:15:00');
    $procedureRecord = recoveryActionCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = $procedureRecord->visit;
    $consultation = $procedureRecord->procedureDecision->consultation;

    $recovery = app(StartRecoveryEpisode::class)->handle($nurse, $procedureRecord);

    expect($recovery->recovery_number)->toBe(sprintf('REC-%06d', $recovery->id))
        ->and($recovery->status)->toBe(RecoveryEpisodeStatus::InProgress)
        ->and($recovery->started_at->toDateTimeString())->toBe('2026-09-09 10:15:00')
        ->and($recovery->completed_at)->toBeNull()
        ->and($recovery->visit_id)->toBe($visit->id)
        ->and($recovery->procedure_record_id)->toBe($procedureRecord->id)
        ->and($recovery->nurse_user_id)->toBe($nurse->id)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Recovery in progress')
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($procedureRecord->fresh()->status)->toBe(ProcedureRecordStatus::Completed)
        ->and($procedureRecord->fresh()->completed_at?->toDateTimeString())->not->toBeNull();

    $audit = AuditLog::query()->where('action', AuditAction::RecoveryStarted)->sole();
    $encodedAudit = json_encode($audit->toArray());

    expect($audit->actor_id)->toBe($nurse->id)
        ->and($audit->subject_type)->toBe(RecoveryEpisode::class)
        ->and($audit->subject_id)->toBe($recovery->id)
        ->and($audit->after_values)->toHaveCount(7)
        ->and($audit->after_values)->toHaveKey('recovery_number', $recovery->recovery_number)
        ->and($audit->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($audit->after_values)->toHaveKey('visit_number', $visit->visit_number)
        ->and($audit->after_values)->toHaveKey('procedure_record_id', $procedureRecord->id)
        ->and($audit->after_values)->toHaveKey('procedure_number', $procedureRecord->procedure_number)
        ->and($audit->after_values)->toHaveKey('nurse_user_id', $nurse->id)
        ->and($audit->after_values)->toHaveKey('status', RecoveryEpisodeStatus::InProgress->value)
        ->and($encodedAudit)->not->toContain('findings')
        ->and($encodedAudit)->not->toContain('outcome')
        ->and($encodedAudit)->not->toContain('price')
        ->and($encodedAudit)->not->toContain('payment');
});

it('trusts the completed Procedure Record without reconstructing upstream module internals', function () {
    $procedureRecord = recoveryActionCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    expect($procedureRecord->visit->procedureBill()->exists())->toBeFalse();

    $recovery = app(StartRecoveryEpisode::class)->handle($nurse, $procedureRecord);

    expect($recovery)->toBeInstanceOf(RecoveryEpisode::class)
        ->and($procedureRecord->visit->fresh()->workflowMessage())->toBe('Recovery in progress');
});

it('rejects an incomplete Procedure Record without a recovery record or audit', function () {
    $procedureRecord = recoveryActionCompletedProcedure(completed: false);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    expect(fn () => app(StartRecoveryEpisode::class)->handle($nurse, $procedureRecord))
        ->toThrow(ValidationException::class, 'completed Procedure Record');

    expect(RecoveryEpisode::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryStarted)->count())->toBe(0);
});

it('rejects duplicate sequential and concurrency-style starts with one record owner and audit', function () {
    $procedureRecord = recoveryActionCompletedProcedure();
    $firstNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $secondNurse = User::factory()->forRole(StaffRole::Nurse)->create();

    $first = app(StartRecoveryEpisode::class)->handle($firstNurse, $procedureRecord);

    expect(fn () => app(StartRecoveryEpisode::class)->handle($secondNurse, $procedureRecord))
        ->toThrow(ValidationException::class, 'already started');

    expect(RecoveryEpisode::query()->count())->toBe(1)
        ->and(RecoveryEpisode::query()->sole()->is($first))->toBeTrue()
        ->and(RecoveryEpisode::query()->sole()->nurse_user_id)->toBe($firstNurse->id)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryStarted)->count())->toBe(1);
});

it('authorizes only an active Nurse to start recovery', function (StaffRole $role) {
    $procedureRecord = recoveryActionCompletedProcedure();
    $actor = User::factory()->forRole($role)->create();

    expect(fn () => app(StartRecoveryEpisode::class)->handle($actor, $procedureRecord))
        ->toThrow(AuthorizationException::class);

    expect(RecoveryEpisode::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryStarted)->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('rejects an inactive Nurse without a record or audit', function () {
    $procedureRecord = recoveryActionCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->inactive()->create();

    expect(fn () => app(StartRecoveryEpisode::class)->handle($nurse, $procedureRecord))
        ->toThrow(AuthorizationException::class);

    expect(RecoveryEpisode::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryStarted)->count())->toBe(0);
});

it('rolls back recovery creation when its audit write fails', function () {
    $procedureRecord = recoveryActionCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')->once()->andThrow(new RuntimeException('Recovery audit failed.'));

    expect(fn () => (new StartRecoveryEpisode($recordAuditLog))->handle($nurse, $procedureRecord))
        ->toThrow(RuntimeException::class, 'Recovery audit failed.');

    expect(RecoveryEpisode::query()->count())->toBe(0)
        ->and($procedureRecord->visit->fresh()->workflowMessage())->toBe('Ready for Nursing recovery');
});

function recoveryActionCompletedProcedure(bool $completed = true): ProcedureRecord
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparationNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $preparationNurse);
    $factory = ProcedureRecord::factory();

    if ($completed) {
        $factory = $factory->completed();
    }

    return $factory->createAuthoritativeProcedureFixture($decision, $readiness);
}
