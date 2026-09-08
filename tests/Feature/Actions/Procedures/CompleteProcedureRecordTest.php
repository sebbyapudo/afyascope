<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Procedures\CompleteProcedureRecord;
use App\Actions\Procedures\StartProcedureRecord;
use App\Actions\Procedures\UpdateProcedureRecordDocumentation;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\ProcedureRecordStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('requires findings and outcome before procedure completion', function () {
    $procedureRecord = procedureCompleteFixture();

    expect(fn () => app(CompleteProcedureRecord::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        1,
    ))->toThrow(ValidationException::class, 'procedure findings');

    app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureCompleteAttributes(['findings' => 'Documented findings.']),
    );

    expect(fn () => app(CompleteProcedureRecord::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        2,
    ))->toThrow(ValidationException::class, 'procedure outcome');

    expect($procedureRecord->fresh()->status)->toBe(ProcedureRecordStatus::InProgress)
        ->and($procedureRecord->fresh()->completed_at)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureCompleted)->count())->toBe(0);
});

it('requires specimen notes only when specimens were taken', function () {
    $procedureRecord = procedureCompleteFixture();
    app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureCompleteAttributes([
            'findings' => 'Documented findings.',
            'outcome' => 'Documented outcome.',
            'specimens_taken' => true,
        ]),
    );

    expect(fn () => app(CompleteProcedureRecord::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        2,
    ))->toThrow(ValidationException::class, 'specimens taken');

    expect($procedureRecord->fresh()->status)->toBe(ProcedureRecordStatus::InProgress);
});

it('completes exactly once and creates the durable Nursing recovery handoff', function () {
    $procedureRecord = procedureCompleteFixture();
    $visit = $procedureRecord->visit;
    $consultation = $procedureRecord->procedureDecision->consultation;
    $decision = $procedureRecord->procedureDecision;
    $readiness = $procedureRecord->preProcedureReadiness;
    app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureCompleteAttributes([
            'findings' => 'Sensitive findings narrative.',
            'diagnosis_impression' => 'Sensitive diagnosis narrative.',
            'outcome' => 'Completed without immediate complication.',
            'procedure_notes' => 'Sensitive procedure narrative.',
        ]),
    );

    $completed = app(CompleteProcedureRecord::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        2,
    );

    expect($completed->status)->toBe(ProcedureRecordStatus::Completed)
        ->and($completed->completed_at)->not->toBeNull()
        ->and($completed->lock_version)->toBe(3)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Nursing recovery')
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($decision->fresh()->service_catalog_item_id)->toBe($decision->service_catalog_item_id)
        ->and($decision->fresh()->doctor_user_id)->toBe($decision->doctor_user_id)
        ->and($readiness->fresh()->status->value)->toBe('ready')
        ->and($readiness->fresh()->nurse_user_id)->toBe($readiness->nurse_user_id)
        ->and(Schema::hasTable('recovery_records'))->toBeFalse()
        ->and(Schema::hasTable('discharges'))->toBeFalse();

    $audit = AuditLog::query()->where('action', AuditAction::ProcedureCompleted)->sole();
    $encodedAudit = json_encode($audit->toArray());

    expect($audit->after_values)->toHaveCount(6)
        ->and($audit->after_values)->toHaveKey('procedure_number', $completed->procedure_number)
        ->and($audit->after_values)->toHaveKey('status', ProcedureRecordStatus::Completed->value)
        ->and($encodedAudit)->not->toContain('Sensitive findings narrative.')
        ->and($encodedAudit)->not->toContain('Sensitive diagnosis narrative.')
        ->and($encodedAudit)->not->toContain('Sensitive procedure narrative.');

    expect(fn () => app(CompleteProcedureRecord::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        3,
    ))->toThrow(ValidationException::class);

    expect(ProcedureRecord::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureCompleted)->count())->toBe(1);
});

it('rejects stale completion without changing lifecycle or audit', function () {
    $procedureRecord = procedureCompleteFixture();
    app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureCompleteAttributes([
            'findings' => 'Documented findings.',
            'outcome' => 'Documented outcome.',
        ]),
    );

    expect(fn () => app(CompleteProcedureRecord::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        1,
    ))->toThrow(ValidationException::class, 'Reload before completing');

    expect($procedureRecord->fresh()->status)->toBe(ProcedureRecordStatus::InProgress)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureCompleted)->count())->toBe(0);
});

it('denies another Doctor from completing the procedure', function () {
    $procedureRecord = procedureCompleteFixture();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();

    expect(fn () => app(CompleteProcedureRecord::class)->handle(
        $otherDoctor,
        $procedureRecord,
        1,
    ))->toThrow(AuthorizationException::class);

    expect($procedureRecord->fresh()->status)->toBe(ProcedureRecordStatus::InProgress);
});

it('rolls back completion when its audit write fails', function () {
    $procedureRecord = procedureCompleteFixture();
    app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureCompleteAttributes([
            'findings' => 'Documented findings.',
            'outcome' => 'Documented outcome.',
        ]),
    );
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')->once()->andThrow(
        new RuntimeException('Completion audit failed.'),
    );

    expect(fn () => (new CompleteProcedureRecord($recordAuditLog))->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        2,
    ))->toThrow(RuntimeException::class, 'Completion audit failed.');

    expect($procedureRecord->fresh()->status)->toBe(ProcedureRecordStatus::InProgress)
        ->and($procedureRecord->fresh()->completed_at)->toBeNull()
        ->and($procedureRecord->fresh()->lock_version)->toBe(2);
});

function procedureCompleteFixture(): ProcedureRecord
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);

    return app(StartProcedureRecord::class)->handle($decision->doctor, $decision->visit);
}

/** @param array<string, bool|int|string|null> $overrides */
function procedureCompleteAttributes(array $overrides = []): array
{
    return [
        'expected_lock_version' => 1,
        'findings' => null,
        'diagnosis_impression' => null,
        'specimens_taken' => false,
        'specimen_notes' => null,
        'complications' => null,
        'outcome' => null,
        'procedure_notes' => null,
        ...$overrides,
    ];
}
