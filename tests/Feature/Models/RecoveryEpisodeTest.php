<?php

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores the minimal recovery aggregate and derives Patient through Visit', function () {
    $procedureRecord = completedProcedureRecordForRecoveryModel();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    $recoveryEpisode = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
        $procedureRecord,
        $nurse,
    );

    expect(Schema::hasColumns('recovery_episodes', [
        'visit_id',
        'procedure_record_id',
        'nurse_user_id',
        'recovery_number',
        'status',
        'started_at',
        'completed_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('recovery_episodes', 'patient_id'))->toBeFalse()
        ->and($recoveryEpisode->visit->is($procedureRecord->visit))->toBeTrue()
        ->and($recoveryEpisode->procedureRecord->is($procedureRecord))->toBeTrue()
        ->and($recoveryEpisode->nurse->is($nurse))->toBeTrue()
        ->and($recoveryEpisode->visit->patient->is($procedureRecord->visit->patient))->toBeTrue()
        ->and($procedureRecord->fresh()->recoveryEpisode->is($recoveryEpisode))->toBeTrue()
        ->and($procedureRecord->visit->fresh()->recoveryEpisode->is($recoveryEpisode))->toBeTrue()
        ->and($recoveryEpisode->status)->toBe(RecoveryEpisodeStatus::InProgress)
        ->and($recoveryEpisode->started_at)->not->toBeNull()
        ->and($recoveryEpisode->completed_at)->toBeNull();
});

it('generates immutable sequential references from database identifiers', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $first = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
        completedProcedureRecordForRecoveryModel(),
        $nurse,
    );
    $second = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
        completedProcedureRecordForRecoveryModel(),
        $nurse,
    );

    expect($first->recovery_number)->toBe(sprintf('REC-%06d', $first->id))
        ->and($second->recovery_number)->toBe(sprintf('REC-%06d', $second->id))
        ->and($second->id)->toBeGreaterThan($first->id);

    $second->recovery_number = 'REC-FORGED';

    expect(fn () => $second->save())
        ->toThrow(LogicException::class, 'future Nursing workflow');
});

it('rejects direct creation and arbitrary lifecycle context or timestamp changes', function () {
    $procedureRecord = completedProcedureRecordForRecoveryModel();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $forged = new RecoveryEpisode;
    $forged->visit()->associate($procedureRecord->visit);
    $forged->procedureRecord()->associate($procedureRecord);
    $forged->nurse()->associate($nurse);
    $forged->recovery_number = 'REC-999999';
    $forged->status = RecoveryEpisodeStatus::Completed;
    $forged->started_at = now()->subHour();
    $forged->completed_at = now();

    expect(fn () => $forged->save())
        ->toThrow(LogicException::class, 'future authoritative Nursing workflow');

    $recoveryEpisode = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
        $procedureRecord,
        $nurse,
    );
    $recoveryEpisode->status = RecoveryEpisodeStatus::Completed;
    $recoveryEpisode->completed_at = now();

    expect(fn () => $recoveryEpisode->save())
        ->toThrow(LogicException::class, 'future Nursing workflow');

    $recoveryEpisode = $recoveryEpisode->fresh();
    $recoveryEpisode->started_at = $recoveryEpisode->started_at->subHour();

    expect(fn () => $recoveryEpisode->save())
        ->toThrow(LogicException::class, 'future Nursing workflow');
});

it('enforces one recovery episode per Visit and Procedure Record at the database boundary', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $firstProcedureRecord = completedProcedureRecordForRecoveryModel();
    $secondProcedureRecord = completedProcedureRecordForRecoveryModel();
    $recoveryEpisode = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
        $firstProcedureRecord,
        $nurse,
    );

    expect(fn () => DB::table('recovery_episodes')->insert(recoveryEpisodeRow([
        'visit_id' => $recoveryEpisode->visit_id,
        'procedure_record_id' => $secondProcedureRecord->id,
        'recovery_number' => 'REC-999998',
        'nurse_user_id' => $nurse->id,
    ])))->toThrow(QueryException::class);

    expect(fn () => DB::table('recovery_episodes')->insert(recoveryEpisodeRow([
        'visit_id' => $secondProcedureRecord->visit_id,
        'procedure_record_id' => $recoveryEpisode->procedure_record_id,
        'recovery_number' => 'REC-999999',
        'nurse_user_id' => $nurse->id,
    ])))->toThrow(QueryException::class);

    expect(RecoveryEpisode::query()->count())->toBe(1);
});

it('uses restrictive history links and database lifecycle constraints', function () {
    $procedureRecord = completedProcedureRecordForRecoveryModel();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recoveryEpisode = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
        $procedureRecord,
        $nurse,
    );

    expect(fn () => DB::table('recovery_episodes')->where('id', $recoveryEpisode->id)->update([
        'status' => RecoveryEpisodeStatus::Completed->value,
    ]))->toThrow(QueryException::class);

    expect(fn () => $procedureRecord->visit->delete())->toThrow(QueryException::class)
        ->and(fn () => $procedureRecord->delete())->toThrow(LogicException::class)
        ->and(fn () => $nurse->delete())->toThrow(QueryException::class)
        ->and(fn () => $recoveryEpisode->delete())->toThrow(LogicException::class);
});

it('requires completed procedure and active Nurse fixtures', function () {
    $procedureRecord = completedProcedureRecordForRecoveryModel(completed: false);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    expect(fn () => RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
        $procedureRecord,
        $nurse,
    ))->toThrow(LogicException::class, 'completed Procedure Record');

    $procedureRecord = completedProcedureRecordForRecoveryModel();

    expect(fn () => RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
        $procedureRecord,
        $doctor,
    ))->toThrow(LogicException::class, 'responsible active Nurse');
});

/** @param array<string, mixed> $overrides */
function recoveryEpisodeRow(array $overrides): array
{
    return array_merge([
        'status' => RecoveryEpisodeStatus::InProgress->value,
        'started_at' => now(),
        'completed_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

function completedProcedureRecordForRecoveryModel(bool $completed = true): ProcedureRecord
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparationNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $preparationNurse);
    $procedureFactory = ProcedureRecord::factory();

    if ($completed) {
        $procedureFactory = $procedureFactory->completed();
    }

    return $procedureFactory->createAuthoritativeProcedureFixture($decision, $readiness);
}
