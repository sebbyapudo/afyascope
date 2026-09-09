<?php

use App\Actions\Nursing\StartRecoveryEpisode;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryObservation;
use App\Models\User;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use LogicException;

it('has the durable serial observation schema and relationships', function () {
    expect(Schema::hasColumns('recovery_observations', [
        'id', 'recovery_episode_id', 'recorded_by_user_id', 'general_recovery_status',
        'pain_score', 'nausea', 'vomiting', 'systolic_blood_pressure',
        'diastolic_blood_pressure', 'pulse_rate', 'respiratory_rate',
        'oxygen_saturation', 'supplemental_oxygen', 'nursing_note', 'recorded_at',
    ]))->toBeTrue();

    [$recovery, $nurse] = observationModelRecovery();
    $observation = RecoveryObservation::factory()->createAuthoritativeObservationFixture($recovery, $nurse);

    expect($observation->recoveryEpisode->is($recovery))->toBeTrue()
        ->and($observation->recordedBy->is($nurse))->toBeTrue()
        ->and($recovery->observations()->sole()->is($observation))->toBeTrue();
});

it('blocks direct creation mutation and deletion of observations', function () {
    [$recovery, $nurse] = observationModelRecovery();

    expect(function (): void {
        $observation = new RecoveryObservation;
        $observation->save();
    })->toThrow(LogicException::class, 'authoritative Nursing workflow');

    $observation = RecoveryObservation::factory()->createAuthoritativeObservationFixture($recovery, $nurse);
    expect(function () use ($observation): void {
        $observation->general_recovery_status = 'Changed';
        $observation->save();
    })->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $observation->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('enforces vital ranges and paired blood pressure at the database boundary', function () {
    [$recovery, $nurse] = observationModelRecovery();

    expect(fn () => RecoveryObservation::factory()
        ->state(['pain_score' => 11])
        ->createAuthoritativeObservationFixture($recovery, $nurse))
        ->toThrow(QueryException::class);
});

/** @return array{0: RecoveryEpisode, 1: User} */
function observationModelRecovery(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $readiness);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    return [app(StartRecoveryEpisode::class)->handle($nurse, $procedure), $nurse];
}
