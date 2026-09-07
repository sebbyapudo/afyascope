<?php

use App\Actions\Nursing\UpdatePreProcedureReadiness;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('persists Nurse-owned checks and normalized observations without a business audit', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $nurse,
    );

    $updated = app(UpdatePreProcedureReadiness::class)->handle($nurse, $readiness, [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => false,
        'medications_reviewed' => false,
        'observations' => '  Baseline observations recorded.  ',
    ]);

    expect($updated->consent_verified)->toBeTrue()
        ->and($updated->patient_identity_verified)->toBeTrue()
        ->and($updated->procedure_verified)->toBeTrue()
        ->and($updated->allergies_reviewed)->toBeFalse()
        ->and($updated->medications_reviewed)->toBeFalse()
        ->and($updated->observations)->toBe('Baseline observations recorded.')
        ->and(AuditLog::query()->whereIn('action', [
            AuditAction::NursingPreparationStarted,
            AuditAction::NursingReadinessCompleted,
        ])->count())->toBe(0);
});

it('does not let another Nurse modify or take over preparation', function () {
    $responsibleNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $responsibleNurse,
    );

    expect(fn () => app(UpdatePreProcedureReadiness::class)->handle(
        $otherNurse,
        $readiness,
        readinessUpdateAttributes(),
    ))->toThrow(AuthorizationException::class);

    expect($readiness->fresh()->nurse_user_id)->toBe($responsibleNurse->id)
        ->and($readiness->fresh()->consent_verified)->toBeFalse();
});

it('rejects forged server-controlled readiness fields', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $nurse,
    );

    expect(fn () => app(UpdatePreProcedureReadiness::class)->handle(
        $nurse,
        $readiness,
        [
            ...readinessUpdateAttributes(),
            'nurse_user_id' => User::factory()->forRole(StaffRole::Nurse)->create()->id,
            'status' => 'ready',
            'readiness_number' => 'PPR-FORGED',
            'completed_at' => now()->toDateTimeString(),
        ],
    ))->toThrow(ValidationException::class);

    expect($readiness->fresh()->nurse_user_id)->toBe($nurse->id)
        ->and($readiness->fresh()->status->value)->toBe('in_preparation')
        ->and($readiness->fresh()->completed_at)->toBeNull();
});

it('does not allow a completed readiness record to be updated', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);

    expect(fn () => app(UpdatePreProcedureReadiness::class)->handle(
        $nurse,
        $readiness,
        readinessUpdateAttributes(),
    ))->toThrow(AuthorizationException::class);
});

/** @return array<string, bool|string|null> */
function readinessUpdateAttributes(): array
{
    return [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => true,
        'medications_reviewed' => true,
        'observations' => null,
    ];
}
