<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Procedures\StartProcedureRecord;
use App\Actions\Procedures\UpdateProcedureRecordDocumentation;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('persists normalized Doctor documentation and audits only changed field names', function () {
    $procedureRecord = procedureUpdateFixture();
    $attributes = procedureUpdateAttributes([
        'findings' => '  Normal mucosal appearance.  ',
        'diagnosis_impression' => '  No acute abnormality.  ',
        'specimens_taken' => true,
        'specimen_notes' => '  One biopsy specimen.  ',
        'complications' => '   ',
        'outcome' => '  Completed without immediate complication.  ',
        'procedure_notes' => '  Routine technique used.  ',
    ]);

    $updated = app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        $attributes,
    );

    expect($updated->findings)->toBe('Normal mucosal appearance.')
        ->and($updated->diagnosis_impression)->toBe('No acute abnormality.')
        ->and($updated->specimens_taken)->toBeTrue()
        ->and($updated->specimen_notes)->toBe('One biopsy specimen.')
        ->and($updated->complications)->toBeNull()
        ->and($updated->outcome)->toBe('Completed without immediate complication.')
        ->and($updated->procedure_notes)->toBe('Routine technique used.')
        ->and($updated->lock_version)->toBe(2);

    $audit = AuditLog::query()
        ->where('action', AuditAction::ProcedureDocumentationUpdated)
        ->sole();
    $encodedAudit = json_encode($audit->toArray());

    expect($audit->after_values)->toHaveKey('changed_fields')
        ->and($audit->after_values['changed_fields'])->toEqual([
            'findings',
            'diagnosis_impression',
            'specimens_taken',
            'specimen_notes',
            'outcome',
            'procedure_notes',
        ])
        ->and($encodedAudit)->not->toContain('Normal mucosal appearance.')
        ->and($encodedAudit)->not->toContain('No acute abnormality.')
        ->and($encodedAudit)->not->toContain('One biopsy specimen.')
        ->and($encodedAudit)->not->toContain('Routine technique used.');
});

it('allows optional non-applicable documentation to remain null', function () {
    $procedureRecord = procedureUpdateFixture();

    $updated = app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureUpdateAttributes(['findings' => 'Documented findings.']),
    );

    expect($updated->findings)->toBe('Documented findings.')
        ->and($updated->diagnosis_impression)->toBeNull()
        ->and($updated->specimens_taken)->toBeFalse()
        ->and($updated->specimen_notes)->toBeNull()
        ->and($updated->complications)->toBeNull()
        ->and($updated->outcome)->toBeNull()
        ->and($updated->procedure_notes)->toBeNull();
});

it('does not audit or advance concurrency state for no-op documentation', function () {
    $procedureRecord = procedureUpdateFixture();

    $updated = app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureUpdateAttributes(),
    );

    expect($updated->lock_version)->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureDocumentationUpdated)->count())->toBe(0);
});

it('rejects stale documentation updates without changing the record or audit', function () {
    $procedureRecord = procedureUpdateFixture();
    app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureUpdateAttributes(['findings' => 'Current findings.']),
    );

    expect(fn () => app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureUpdateAttributes([
            'expected_lock_version' => 1,
            'findings' => 'Stale findings.',
        ]),
    ))->toThrow(ValidationException::class, 'Reload before saving');

    expect($procedureRecord->fresh()->findings)->toBe('Current findings.')
        ->and($procedureRecord->fresh()->lock_version)->toBe(2)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureDocumentationUpdated)->count())->toBe(1);
});

it('denies another Doctor from updating or taking ownership', function () {
    $procedureRecord = procedureUpdateFixture();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();

    expect(fn () => app(UpdateProcedureRecordDocumentation::class)->handle(
        $otherDoctor,
        $procedureRecord,
        procedureUpdateAttributes(['findings' => 'Unauthorized findings.']),
    ))->toThrow(AuthorizationException::class);

    expect($procedureRecord->fresh()->doctor_user_id)->toBe($procedureRecord->doctor_user_id)
        ->and($procedureRecord->fresh()->findings)->toBeNull();
});

it('rejects forged context lifecycle and server concurrency fields', function () {
    $procedureRecord = procedureUpdateFixture();

    expect(fn () => app(UpdateProcedureRecordDocumentation::class)->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        [
            ...procedureUpdateAttributes(),
            'doctor_user_id' => User::factory()->forRole(StaffRole::Doctor)->create()->id,
            'status' => 'completed',
            'procedure_number' => 'PRC-FORGED',
            'lock_version' => 99,
            'completed_at' => now()->toDateTimeString(),
        ],
    ))->toThrow(ValidationException::class);

    expect($procedureRecord->fresh()->status->value)->toBe('in_progress')
        ->and($procedureRecord->fresh()->procedure_number)->toBe($procedureRecord->procedure_number)
        ->and($procedureRecord->fresh()->lock_version)->toBe(1);
});

it('rolls back documentation when its structural audit fails', function () {
    $procedureRecord = procedureUpdateFixture();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')->once()->andThrow(
        new RuntimeException('Documentation audit failed.'),
    );

    expect(fn () => (new UpdateProcedureRecordDocumentation($recordAuditLog))->handle(
        $procedureRecord->doctor,
        $procedureRecord,
        procedureUpdateAttributes(['findings' => 'Rolled-back findings.']),
    ))->toThrow(RuntimeException::class, 'Documentation audit failed.');

    expect($procedureRecord->fresh()->findings)->toBeNull()
        ->and($procedureRecord->fresh()->lock_version)->toBe(1);
});

function procedureUpdateFixture(): ProcedureRecord
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);

    return app(StartProcedureRecord::class)->handle($decision->doctor, $decision->visit);
}

/** @param array<string, bool|int|string|null> $overrides */
function procedureUpdateAttributes(array $overrides = []): array
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
