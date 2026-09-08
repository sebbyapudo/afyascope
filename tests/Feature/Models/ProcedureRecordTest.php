<?php

use App\Actions\Procedures\StartProcedureRecord;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\ProcedureRecordStatus;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('stores the minimal Visit-scoped procedure domain and relationships', function () {
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);

    $procedureRecord = app(StartProcedureRecord::class)->handle($decision->doctor, $decision->visit);

    expect(Schema::hasColumns('procedure_records', [
        'visit_id',
        'procedure_decision_id',
        'pre_procedure_readiness_id',
        'service_catalog_item_id',
        'doctor_user_id',
        'procedure_number',
        'status',
        'findings',
        'diagnosis_impression',
        'specimens_taken',
        'specimen_notes',
        'complications',
        'outcome',
        'procedure_notes',
        'lock_version',
        'started_at',
        'completed_at',
    ]))->toBeTrue()
        ->and($procedureRecord->visit->is($decision->visit))->toBeTrue()
        ->and($procedureRecord->procedureDecision->is($decision))->toBeTrue()
        ->and($procedureRecord->preProcedureReadiness->is($readiness))->toBeTrue()
        ->and($procedureRecord->serviceCatalogItem->is($decision->serviceCatalogItem))->toBeTrue()
        ->and($procedureRecord->doctor->is($decision->doctor))->toBeTrue()
        ->and($procedureRecord->status)->toBe(ProcedureRecordStatus::InProgress)
        ->and($procedureRecord->procedure_number)->toMatch('/^PRC-\d{6,}$/')
        ->and($procedureRecord->lock_version)->toBe(1);
});

it('rejects direct creation and changes to server-controlled context', function () {
    expect(fn () => ProcedureRecord::factory()->create())
        ->toThrow(LogicException::class, 'authoritative Doctor workflow');

    $procedureRecord = procedureRecordModelFixture();
    $procedureRecord->procedure_number = 'PRC-FORGED';

    expect(fn () => $procedureRecord->save())
        ->toThrow(LogicException::class, 'authoritative context');
});

it('prevents deletion and completed-record mutation', function () {
    $procedureRecord = procedureRecordModelFixture(completed: true);

    expect(fn () => $procedureRecord->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');

    $freshProcedureRecord = $procedureRecord->fresh();
    $freshProcedureRecord->findings = 'Changed findings';

    expect(fn () => $freshProcedureRecord->save())
        ->toThrow(LogicException::class, 'cannot be changed');
});

it('enforces one procedure record per Visit decision and readiness at the database boundary', function () {
    $procedureRecord = procedureRecordModelFixture();

    expect(fn () => ProcedureRecord::factory()->createAuthoritativeProcedureFixture(
        $procedureRecord->procedureDecision,
        $procedureRecord->preProcedureReadiness,
    ))->toThrow(QueryException::class);

    expect(ProcedureRecord::query()->count())->toBe(1);
});

function procedureRecordModelFixture(bool $completed = false): ProcedureRecord
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);
    $factory = ProcedureRecord::factory();

    if ($completed) {
        $factory = $factory->completed();
    }

    return $factory->createAuthoritativeProcedureFixture($decision, $readiness);
}
