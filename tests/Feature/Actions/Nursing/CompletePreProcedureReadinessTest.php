<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Nursing\CompletePreProcedureReadiness;
use App\Actions\Nursing\UpdatePreProcedureReadiness;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\PaymentMethod;
use App\PreProcedureReadinessStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Validation\ValidationException;

it('rejects readiness completion until every mandatory check including consent is verified', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = readinessCompleteClearedDecision();
    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $nurse,
        [
            'patient_identity_verified' => true,
            'procedure_verified' => true,
            'allergies_reviewed' => true,
            'medications_reviewed' => true,
        ],
    );

    expect(fn () => app(CompletePreProcedureReadiness::class)->handle(
        $nurse,
        $readiness,
    ))->toThrow(ValidationException::class);

    expect($readiness->fresh()->status)->toBe(PreProcedureReadinessStatus::InPreparation)
        ->and($readiness->fresh()->completed_at)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::NursingReadinessCompleted)->count())->toBe(0);
});

it('completes readiness exactly once without changing upstream ownership or lifecycle', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = readinessCompleteClearedDecision();
    $visit = $decision->visit;
    $consultation = $decision->consultation;
    $handoff = $decision->procedureBillingHandoff;
    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $nurse,
    );
    app(UpdatePreProcedureReadiness::class)->handle($nurse, $readiness, [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => true,
        'medications_reviewed' => true,
        'observations' => 'Sensitive clinical preparation narrative.',
    ]);

    $completed = app(CompletePreProcedureReadiness::class)->handle($nurse, $readiness);

    expect($completed->status)->toBe(PreProcedureReadinessStatus::Ready)
        ->and($completed->completed_at)->not->toBeNull()
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Doctor procedure')
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($decision->fresh()->service_catalog_item_id)->toBe($decision->service_catalog_item_id)
        ->and($decision->fresh()->doctor_user_id)->toBe($decision->doctor_user_id)
        ->and($handoff?->fresh()->procedure_decision_id)->toBe($decision->id);

    $audit = AuditLog::query()
        ->where('action', AuditAction::NursingReadinessCompleted)
        ->sole();

    expect($audit->after_values)->toHaveCount(5)
        ->and($audit->after_values)->toHaveKey('readiness_number', $completed->readiness_number)
        ->and($audit->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($audit->after_values)->toHaveKey('procedure_decision_id', $decision->id)
        ->and($audit->after_values)->toHaveKey('nurse_user_id', $nurse->id)
        ->and($audit->after_values)->toHaveKey('status', PreProcedureReadinessStatus::Ready->value)
        ->and(json_encode($audit->toArray()))->not->toContain('Sensitive clinical preparation narrative.');

    expect(fn () => app(CompletePreProcedureReadiness::class)->handle(
        $nurse,
        $readiness,
    ))->toThrow(ValidationException::class);

    expect(PreProcedureReadiness::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::NursingReadinessCompleted)->count())->toBe(1);
});

it('rolls back readiness completion when its audit write fails', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()
        ->createAuthoritativePreparationFixture($decision, $nurse, [
            'consent_verified' => true,
            'patient_identity_verified' => true,
            'procedure_verified' => true,
            'allergies_reviewed' => true,
            'medications_reviewed' => true,
        ]);
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')->once()->andThrow(
        new RuntimeException('Completion audit failed.'),
    );

    expect(fn () => (new CompletePreProcedureReadiness($recordAuditLog))->handle(
        $nurse,
        $readiness,
    ))->toThrow(RuntimeException::class, 'Completion audit failed.');

    expect($readiness->fresh()->status)->toBe(PreProcedureReadinessStatus::InPreparation)
        ->and($readiness->fresh()->completed_at)->toBeNull();
});

function readinessCompleteClearedDecision(): ProcedureDecision
{
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);
    app(GrantProcedureFinancialClearance::class)->handle($accountant, $bill);

    return $handoff->procedureDecision->fresh();
}
