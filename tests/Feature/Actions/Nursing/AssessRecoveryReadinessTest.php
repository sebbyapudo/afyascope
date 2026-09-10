<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\RecoveryReadinessAssessment;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('marks an uncomplicated recovery ready for discharge without discharging the patient', function () {
    [$recovery, $nurse] = readinessActionRecovery();

    $assessment = app(AssessRecoveryReadiness::class)->handle(
        $nurse,
        $recovery,
        readinessAttributes(['criteria_met' => true]),
    );

    expect($assessment->criteria_met)->toBeTrue()
        ->and($assessment->clinical_concern_requires_escalation)->toBeFalse()
        ->and($assessment->assessed_by_user_id)->toBe($nurse->id)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::ReadyForDischarge)
        ->and($recovery->visit->fresh()->workflowMessage())->toBe('Ready for discharge')
        ->and(RecoveryEscalation::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryReadinessAssessed)->count())->toBe(1);
});

it('keeps one current assessment and permits an auditable Nurse reassessment while recovery continues', function () {
    [$recovery, $nurse] = readinessActionRecovery();

    $first = app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, readinessAttributes());
    $this->travel(5)->minutes();
    $second = app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, readinessAttributes([
        'assessment_note' => 'Criteria remain under review.',
    ]));

    expect($second->id)->toBe($first->id)
        ->and($second->assessed_at->greaterThan($first->assessed_at))->toBeTrue()
        ->and(RecoveryReadinessAssessment::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryReadinessAssessed)->count())->toBe(2)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::InProgress);
});

it('creates a durable open escalation and blocks discharge readiness until Doctor review', function () {
    [$recovery, $nurse] = readinessActionRecovery();
    $reason = 'Persistent oxygen requirement requires clinical review.';

    $assessment = app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, readinessAttributes([
        'clinical_concern_requires_escalation' => true,
        'escalation_reason' => $reason,
    ]));
    $escalation = RecoveryEscalation::query()->sole();
    $audits = AuditLog::query()
        ->whereIn('action', [
            AuditAction::RecoveryReadinessAssessed,
            AuditAction::RecoveryEscalated,
        ])->get();
    $encodedAudits = json_encode($audits->toArray());

    expect($assessment->clinical_concern_requires_escalation)->toBeTrue()
        ->and($escalation->reason)->toBe($reason)
        ->and($escalation->escalated_by_user_id)->toBe($nurse->id)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::InProgress)
        ->and($recovery->visit->fresh()->workflowMessage())->toBe('Doctor review required')
        ->and($audits)->toHaveCount(2)
        ->and($encodedAudits)->not->toContain($reason)
        ->and($encodedAudits)->not->toContain('assessment_note');

    expect(fn () => app(AssessRecoveryReadiness::class)->handle(
        $nurse,
        $recovery,
        readinessAttributes(['criteria_met' => true]),
    ))->toThrow(AuthorizationException::class);

    expect(RecoveryEscalation::query()->where('open_marker', true)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryEscalated)->count())->toBe(1);
});

it('rejects contradictory or forged readiness state without durable changes', function () {
    [$recovery, $nurse] = readinessActionRecovery();

    expect(fn () => app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, readinessAttributes([
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => true,
        'escalation_reason' => 'Concern.',
        'assessed_by_user_id' => 999,
    ])))->toThrow(ValidationException::class);

    expect(RecoveryReadinessAssessment::query()->count())->toBe(0)
        ->and(RecoveryEscalation::query()->count())->toBe(0)
        ->and(AuditLog::query()->whereIn('action', [
            AuditAction::RecoveryReadinessAssessed,
            AuditAction::RecoveryEscalated,
        ])->count())->toBe(0);
});

it('rejects non-owner Nurses other roles and inactive owners', function () {
    [$recovery, $owner] = readinessActionRecovery();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    expect(fn () => app(AssessRecoveryReadiness::class)->handle($otherNurse, $recovery, readinessAttributes()))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(AssessRecoveryReadiness::class)->handle($doctor, $recovery, readinessAttributes()))
        ->toThrow(AuthorizationException::class);

    User::query()->whereKey($owner->getKey())->update(['is_active' => false]);
    expect(fn () => app(AssessRecoveryReadiness::class)->handle($owner, $recovery, readinessAttributes()))
        ->toThrow(ValidationException::class, 'responsible active Nurse');

    expect(RecoveryReadinessAssessment::query()->count())->toBe(0)
        ->and(RecoveryEscalation::query()->count())->toBe(0);
});

it('rolls back readiness and escalation when audit recording fails', function () {
    [$recovery, $nurse] = readinessActionRecovery();
    $auditRecorder = Mockery::mock(RecordAuditLog::class);
    $auditRecorder->shouldReceive('handle')->twice()->andReturnUsing(function (): AuditLog {
        static $calls = 0;
        $calls++;

        if ($calls === 2) {
            throw new RuntimeException('Audit failed.');
        }

        return new AuditLog;
    });

    expect(fn () => (new AssessRecoveryReadiness($auditRecorder))->handle($nurse, $recovery, readinessAttributes([
        'clinical_concern_requires_escalation' => true,
        'escalation_reason' => 'Doctor review required.',
    ])))->toThrow(RuntimeException::class, 'Audit failed.');

    expect(RecoveryReadinessAssessment::query()->count())->toBe(0)
        ->and(RecoveryEscalation::query()->count())->toBe(0)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::InProgress);
});

/** @return array{0: RecoveryEpisode, 1: User} */
function readinessActionRecovery(): array
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

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function readinessAttributes(array $overrides = []): array
{
    return array_replace([
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => false,
        'assessment_note' => null,
        'escalation_reason' => null,
    ], $overrides);
}
