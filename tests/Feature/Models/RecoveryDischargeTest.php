<?php

use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\RecoveryDischargeAccompanimentStatus;
use App\RecoveryDischargeDisposition;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores one immutable structured discharge record linked through recovery to the Visit', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeModel();

    $discharge = RecoveryDischarge::factory()->createAuthoritativeDischargeFixture($recovery, $nurse);

    expect(Schema::hasColumns('recovery_discharges', [
        'recovery_episode_id',
        'discharged_by_user_id',
        'discharge_number',
        'condition_summary',
        'accompaniment_status',
        'disposition',
        'nursing_note',
        'general_care_instructions',
        'activity_driving_instructions',
        'diet_fluids_instructions',
        'medication_instructions',
        'warning_signs_instructions',
        'follow_up_instructions',
        'discharged_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('recovery_discharges', 'patient_id'))->toBeFalse()
        ->and(Schema::hasColumn('recovery_discharges', 'visit_id'))->toBeFalse()
        ->and($discharge->recoveryEpisode->is($recovery))->toBeTrue()
        ->and($discharge->recoveryEpisode->visit->is($recovery->visit))->toBeTrue()
        ->and($discharge->dischargedBy->is($nurse))->toBeTrue()
        ->and($discharge->accompaniment_status)->toBe(RecoveryDischargeAccompanimentStatus::Accompanied)
        ->and($discharge->disposition)->toBe(RecoveryDischargeDisposition::Home);
});

it('generates stable sequential discharge references and server timestamps', function () {
    [$firstRecovery, $firstNurse] = readyRecoveryForDischargeModel();
    [$secondRecovery, $secondNurse] = readyRecoveryForDischargeModel();
    $this->travelTo('2026-09-11 14:00:00');

    $first = RecoveryDischarge::factory()->createAuthoritativeDischargeFixture($firstRecovery, $firstNurse);
    $second = RecoveryDischarge::factory()->createAuthoritativeDischargeFixture($secondRecovery, $secondNurse);

    expect($first->discharge_number)->toBe(sprintf('DSC-%06d', $first->id))
        ->and($second->discharge_number)->toBe(sprintf('DSC-%06d', $second->id))
        ->and($second->id)->toBeGreaterThan($first->id)
        ->and($first->discharged_at->toDateTimeString())->toBe('2026-09-11 14:00:00');
});

it('rejects direct creation edits and deletion of finalized discharge records', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeModel();
    $direct = new RecoveryDischarge;
    $direct->recoveryEpisode()->associate($recovery);
    $direct->dischargedBy()->associate($nurse);

    expect(fn () => $direct->save())
        ->toThrow(LogicException::class, 'authoritative Nursing workflow');

    $discharge = RecoveryDischarge::factory()->createAuthoritativeDischargeFixture($recovery, $nurse);
    $discharge->condition_summary = 'Silently overwritten condition.';

    expect(fn () => $discharge->save())
        ->toThrow(LogicException::class, 'cannot be changed')
        ->and(fn () => $discharge->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('enforces one discharge per recovery and constrained structured values at the database boundary', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeModel();
    $discharge = RecoveryDischarge::factory()->createAuthoritativeDischargeFixture($recovery, $nurse);

    expect(fn () => DB::table('recovery_discharges')->insert(recoveryDischargeModelRow([
        'recovery_episode_id' => $recovery->id,
        'discharged_by_user_id' => $nurse->id,
        'discharge_number' => 'DSC-999998',
    ])))->toThrow(QueryException::class);

    [$otherRecovery, $otherNurse] = readyRecoveryForDischargeModel();

    expect(fn () => DB::table('recovery_discharges')->insert(recoveryDischargeModelRow([
        'recovery_episode_id' => $otherRecovery->id,
        'discharged_by_user_id' => $otherNurse->id,
        'discharge_number' => 'DSC-999999',
        'accompaniment_status' => 'forged',
    ])))->toThrow(QueryException::class);

    expect(RecoveryDischarge::query()->count())->toBe(1)
        ->and($recovery->fresh()->discharge->is($discharge))->toBeTrue();
});

/** @return array{0: RecoveryEpisode, 1: User} */
function readyRecoveryForDischargeModel(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $preparation);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recovery = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedure, $nurse);
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
    ]);

    return [$recovery->refresh(), $nurse];
}

/** @param array<string, mixed> $overrides */
function recoveryDischargeModelRow(array $overrides): array
{
    return array_merge([
        'condition_summary' => 'Ready for discharge.',
        'accompaniment_status' => RecoveryDischargeAccompanimentStatus::Accompanied->value,
        'disposition' => RecoveryDischargeDisposition::Home->value,
        'nursing_note' => null,
        'general_care_instructions' => 'General care.',
        'activity_driving_instructions' => 'Activity guidance.',
        'diet_fluids_instructions' => 'Diet guidance.',
        'medication_instructions' => null,
        'warning_signs_instructions' => 'Warning signs.',
        'follow_up_instructions' => null,
        'discharged_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}
