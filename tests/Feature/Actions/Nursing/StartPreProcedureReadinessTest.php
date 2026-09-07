<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Nursing\StartPreProcedureReadiness;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\Models\Visit;
use App\PaymentMethod;
use App\PreProcedureReadinessStatus;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('starts one preparation for a financially cleared procedure and assigns the active Nurse', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = readinessStartClearedProcedureVisit();

    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);

    expect($readiness->readiness_number)->toMatch('/^PPR-\d{6,}$/')
        ->and($readiness->nurse->is($nurse))->toBeTrue()
        ->and($readiness->procedureDecision->is($visit->procedureDecision))->toBeTrue()
        ->and($readiness->status)->toBe(PreProcedureReadinessStatus::InPreparation)
        ->and($readiness->started_at)->not->toBeNull()
        ->and($readiness->completed_at)->toBeNull()
        ->and($visit->fresh()->workflowMessage())->toBe('Nursing preparation in progress');

    $audit = AuditLog::query()
        ->where('action', AuditAction::NursingPreparationStarted)
        ->sole();

    expect($audit->actor->is($nurse))->toBeTrue()
        ->and($audit->subject->is($readiness))->toBeTrue()
        ->and($audit->after_values)->toHaveCount(5)
        ->and($audit->after_values)->toHaveKey('readiness_number', $readiness->readiness_number)
        ->and($audit->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($audit->after_values)->toHaveKey('procedure_decision_id', $visit->procedureDecision->id)
        ->and($audit->after_values)->toHaveKey('nurse_user_id', $nurse->id)
        ->and($audit->after_values)->toHaveKey('status', PreProcedureReadinessStatus::InPreparation->value);
});

it('rejects no-procedure and uncleared procedure Visits without a readiness audit', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $noProcedureDecision = ProcedureDecision::factory()->createAuthoritativeDecisionFixture();
    $unpaidHandoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $paidUnclearedHandoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $paidUnclearedBill = app(CreateProcedureBill::class)->handle($accountant, $paidUnclearedHandoff);
    app(RecordProcedurePayment::class)->handle(
        $accountant,
        $paidUnclearedBill,
        PaymentMethod::Cash,
    );

    foreach ([
        $noProcedureDecision->visit,
        $unpaidHandoff->visit,
        $paidUnclearedHandoff->visit,
    ] as $visit) {
        expect(fn () => app(StartPreProcedureReadiness::class)->handle(
            $nurse,
            $visit,
        ))->toThrow(ValidationException::class);
    }

    expect(PreProcedureReadiness::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::NursingPreparationStarted)->count())->toBe(0);
});

it('prevents a second Nurse from taking ownership of an existing preparation', function () {
    $firstNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $secondNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = readinessStartClearedProcedureVisit();

    $readiness = app(StartPreProcedureReadiness::class)->handle($firstNurse, $visit);

    expect(fn () => app(StartPreProcedureReadiness::class)->handle(
        $secondNurse,
        $visit,
    ))->toThrow(ValidationException::class);

    expect(PreProcedureReadiness::query()->count())->toBe(1)
        ->and($readiness->fresh()->nurse_user_id)->toBe($firstNurse->id)
        ->and(AuditLog::query()->where('action', AuditAction::NursingPreparationStarted)->count())->toBe(1);
});

it('enforces the Nurse role and active account at the action boundary', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $visit = readinessStartClearedProcedureVisit();

    expect(fn () => app(StartPreProcedureReadiness::class)->handle(
        $actor,
        $visit,
    ))->toThrow(AuthorizationException::class);

    expect(PreProcedureReadiness::query()->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('denies an inactive Nurse at the action boundary', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->inactive()->create();
    $visit = readinessStartClearedProcedureVisit();

    expect(fn () => app(StartPreProcedureReadiness::class)->handle(
        $nurse,
        $visit,
    ))->toThrow(AuthorizationException::class);

    expect(PreProcedureReadiness::query()->count())->toBe(0);
});

it('rolls back preparation when its audit write fails', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = readinessStartClearedProcedureVisit();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')->once()->andThrow(
        new RuntimeException('Readiness audit failed.'),
    );

    expect(fn () => (new StartPreProcedureReadiness($recordAuditLog))->handle(
        $nurse,
        $visit,
    ))->toThrow(RuntimeException::class, 'Readiness audit failed.');

    expect(PreProcedureReadiness::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::NursingPreparationStarted)->count())->toBe(0);
});

function readinessStartClearedProcedureVisit(): Visit
{
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);
    app(GrantProcedureFinancialClearance::class)->handle($accountant, $bill);

    return $handoff->visit->fresh(['procedureDecision', 'consultation']);
}
