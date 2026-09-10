<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Clinical\ResolveRecoveryEscalation;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\User;
use App\RecoveryEscalationResolution;
use App\RecoveryEscalationStatus;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

it('lets an active Doctor resolve escalation back to continued Nursing monitoring', function () {
    [$recovery, $nurse, $escalation] = clinicalEscalationContext();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    $resolved = app(ResolveRecoveryEscalation::class)->handle($doctor, $escalation, [
        'resolution' => RecoveryEscalationResolution::ContinueMonitoring->value,
        'resolution_note' => 'Continue close recovery observation.',
    ]);

    expect($resolved->status)->toBe(RecoveryEscalationStatus::Resolved)
        ->and($resolved->open_marker)->toBeNull()
        ->and($resolved->resolved_by_user_id)->toBe($doctor->id)
        ->and($resolved->resolution)->toBe(RecoveryEscalationResolution::ContinueMonitoring)
        ->and($recovery->visit->fresh()->workflowMessage())->toBe('Recovery in progress');

    $audit = AuditLog::query()->where('action', AuditAction::RecoveryEscalationResolved)->sole();
    expect($audit->actor_id)->toBe($doctor->id)
        ->and(json_encode($audit->toArray()))->not->toContain('Continue close recovery observation');

    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery->fresh(), [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
    ]);

    expect($recovery->visit->fresh()->workflowMessage())->toBe('Ready for discharge');
});

it('records clinical clearance without Doctor discharge or automatic readiness', function () {
    [$recovery, , $escalation] = clinicalEscalationContext();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    app(ResolveRecoveryEscalation::class)->handle($doctor, $escalation, [
        'resolution' => RecoveryEscalationResolution::ClinicallyCleared->value,
    ]);

    expect($recovery->fresh()->status->value)->toBe('in_progress')
        ->and($recovery->visit->fresh()->workflowMessage())->toBe('Recovery in progress')
        ->and(Route::has('nursing.recovery.discharge'))->toBeFalse();
});

it('rejects duplicate Doctor resolution without duplicate audit', function () {
    [, , $escalation] = clinicalEscalationContext();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $action = app(ResolveRecoveryEscalation::class);

    $action->handle($doctor, $escalation, [
        'resolution' => RecoveryEscalationResolution::ContinueMonitoring->value,
    ]);

    expect(fn () => $action->handle($doctor, $escalation, [
        'resolution' => RecoveryEscalationResolution::ContinueMonitoring->value,
    ]))->toThrow(ValidationException::class, 'already been resolved');

    expect(AuditLog::query()->where('action', AuditAction::RecoveryEscalationResolved)->count())->toBe(1);
});

it('denies all non-Doctors and inactive Doctors from resolving escalation', function () {
    [, , $escalation] = clinicalEscalationContext();

    foreach ([StaffRole::Receptionist, StaffRole::Accountant, StaffRole::Nurse, StaffRole::Administrator, StaffRole::Management] as $role) {
        $actor = User::factory()->forRole($role)->create();

        expect(fn () => app(ResolveRecoveryEscalation::class)->handle($actor, $escalation, [
            'resolution' => RecoveryEscalationResolution::ContinueMonitoring->value,
        ]))->toThrow(AuthorizationException::class);
    }

    $inactiveDoctor = User::factory()->forRole(StaffRole::Doctor)->inactive()->create();
    expect(fn () => app(ResolveRecoveryEscalation::class)->handle($inactiveDoctor, $escalation, [
        'resolution' => RecoveryEscalationResolution::ContinueMonitoring->value,
    ]))->toThrow(AuthorizationException::class);

    expect($escalation->fresh()->status)->toBe(RecoveryEscalationStatus::Open)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryEscalationResolved)->count())->toBe(0);
});

it('rolls back Doctor resolution when audit recording fails', function () {
    [, , $escalation] = clinicalEscalationContext();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $auditRecorder = Mockery::mock(RecordAuditLog::class);
    $auditRecorder->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit failed.'));

    expect(fn () => (new ResolveRecoveryEscalation($auditRecorder))->handle($doctor, $escalation, [
        'resolution' => RecoveryEscalationResolution::ClinicallyCleared->value,
    ]))->toThrow(RuntimeException::class, 'Audit failed.');

    expect($escalation->fresh()->status)->toBe(RecoveryEscalationStatus::Open)
        ->and($escalation->fresh()->resolved_at)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryEscalationResolved)->count())->toBe(0);
});

it('rejects forged resolution ownership and malformed outcomes', function () {
    [, , $escalation] = clinicalEscalationContext();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    expect(fn () => app(ResolveRecoveryEscalation::class)->handle($doctor, $escalation, [
        'resolution' => 'discharged',
        'resolved_by_user_id' => $doctor->id,
    ]))->toThrow(ValidationException::class);

    expect($escalation->fresh()->status)->toBe(RecoveryEscalationStatus::Open);
});

/** @return array{0: RecoveryEpisode, 1: User, 2: RecoveryEscalation} */
function clinicalEscalationContext(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $preparation);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recovery = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedure, $nurse);
    $escalation = RecoveryEscalation::factory()->createAuthoritativeEscalationFixture($recovery, $nurse);

    return [$recovery, $nurse, $escalation];
}
