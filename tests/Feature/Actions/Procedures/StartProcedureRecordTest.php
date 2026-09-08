<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Procedures\StartProcedureRecord;
use App\AuditAction;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\ProcedureRecordStatus;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('starts one procedure from authoritative ready Nursing handoff without reconstructing finances', function () {
    [$decision, $readiness] = procedureStartReadyContext();
    $doctor = $decision->doctor;

    $procedureRecord = app(StartProcedureRecord::class)->handle($doctor, $decision->visit);

    expect($procedureRecord->procedure_number)->toMatch('/^PRC-\d{6,}$/')
        ->and($procedureRecord->status)->toBe(ProcedureRecordStatus::InProgress)
        ->and($procedureRecord->doctor->is($doctor))->toBeTrue()
        ->and($procedureRecord->preProcedureReadiness->is($readiness))->toBeTrue()
        ->and($procedureRecord->service_catalog_item_id)->toBe($decision->service_catalog_item_id)
        ->and($procedureRecord->started_at)->not->toBeNull()
        ->and($procedureRecord->completed_at)->toBeNull()
        ->and(Bill::query()
            ->where('visit_id', $decision->visit_id)
            ->where('type', BillType::Procedure->value)
            ->count())->toBe(0)
        ->and($decision->visit->fresh()->workflowMessage())->toBe('Procedure in progress');

    $audit = AuditLog::query()->where('action', AuditAction::ProcedureStarted)->sole();

    expect($audit->actor->is($doctor))->toBeTrue()
        ->and($audit->subject->is($procedureRecord))->toBeTrue()
        ->and($audit->after_values)->toHaveCount(6)
        ->and($audit->after_values)->toHaveKey('procedure_number', $procedureRecord->procedure_number)
        ->and($audit->after_values)->toHaveKey('visit_id', $decision->visit_id)
        ->and($audit->after_values)->toHaveKey('procedure_decision_id', $decision->id)
        ->and($audit->after_values)->toHaveKey('pre_procedure_readiness_id', $readiness->id)
        ->and($audit->after_values)->toHaveKey('doctor_user_id', $doctor->id)
        ->and($audit->after_values)->toHaveKey('status', ProcedureRecordStatus::InProgress->value);
});

it('rejects in-progress readiness and no-procedure decisions', function () {
    $inPreparationDecision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $inPreparationDecision,
        $nurse,
    );
    $noProcedureDecision = ProcedureDecision::factory()->createAuthoritativeDecisionFixture();

    foreach ([$inPreparationDecision, $noProcedureDecision] as $decision) {
        expect(fn () => app(StartProcedureRecord::class)->handle(
            $decision->doctor,
            $decision->visit,
        ))->toThrow(ValidationException::class);
    }

    expect(ProcedureRecord::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureStarted)->count())->toBe(0);
});

it('rejects a different Doctor without allowing takeover', function () {
    [$decision] = procedureStartReadyContext();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();

    expect(fn () => app(StartProcedureRecord::class)->handle(
        $otherDoctor,
        $decision->visit,
    ))->toThrow(ValidationException::class);

    expect(ProcedureRecord::query()->count())->toBe(0);
});

it('denies all non-Doctor roles at the action boundary', function (StaffRole $role) {
    [$decision] = procedureStartReadyContext();
    $actor = User::factory()->forRole($role)->create();

    expect(fn () => app(StartProcedureRecord::class)->handle(
        $actor,
        $decision->visit,
    ))->toThrow(AuthorizationException::class);

    expect(ProcedureRecord::query()->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('denies an inactive responsible Doctor', function () {
    [$decision] = procedureStartReadyContext();
    $decision->doctor->is_active = false;
    $decision->doctor->save();

    expect(fn () => app(StartProcedureRecord::class)->handle(
        $decision->doctor,
        $decision->visit,
    ))->toThrow(AuthorizationException::class);

    expect(ProcedureRecord::query()->count())->toBe(0);
});

it('protects duplicate starts with one authoritative record and audit', function () {
    [$decision] = procedureStartReadyContext();

    app(StartProcedureRecord::class)->handle($decision->doctor, $decision->visit);

    expect(fn () => app(StartProcedureRecord::class)->handle(
        $decision->doctor,
        $decision->visit,
    ))->toThrow(ValidationException::class);

    expect(ProcedureRecord::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureStarted)->count())->toBe(1);
});

it('rejects a cross-Visit readiness mismatch', function () {
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    [$otherDecision, $otherReadiness] = procedureStartReadyContext();
    $otherReadiness->visit_id = $decision->visit_id;
    $otherReadiness->saveQuietly();

    expect(fn () => app(StartProcedureRecord::class)->handle(
        $decision->doctor,
        $decision->visit,
    ))->toThrow(ValidationException::class);

    expect(ProcedureRecord::query()->count())->toBe(0)
        ->and($otherDecision->fresh()->visit_id)->not->toBe($decision->visit_id);
});

it('rolls back start when the audit write fails', function () {
    [$decision] = procedureStartReadyContext();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')->once()->andThrow(
        new RuntimeException('Procedure audit failed.'),
    );

    expect(fn () => (new StartProcedureRecord($recordAuditLog))->handle(
        $decision->doctor,
        $decision->visit,
    ))->toThrow(RuntimeException::class, 'Procedure audit failed.');

    expect(ProcedureRecord::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureStarted)->count())->toBe(0);
});

/** @return array{ProcedureDecision, PreProcedureReadiness} */
function procedureStartReadyContext(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);

    return [$decision, $readiness];
}
