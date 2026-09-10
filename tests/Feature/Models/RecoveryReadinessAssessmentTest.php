<?php

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryReadinessAssessment;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores one current structured readiness assessment linked to recovery and Nurse', function () {
    [$recovery, $nurse] = readinessAssessmentModelRecovery();
    $assessment = RecoveryReadinessAssessment::factory()
        ->createAuthoritativeAssessmentFixture($recovery, $nurse);

    expect(Schema::hasColumns('recovery_readiness_assessments', [
        'recovery_episode_id',
        'assessed_by_user_id',
        'criteria_met',
        'clinical_concern_requires_escalation',
        'assessment_note',
        'assessed_at',
    ]))->toBeTrue()
        ->and($assessment->recoveryEpisode->is($recovery))->toBeTrue()
        ->and($assessment->assessedBy->is($nurse))->toBeTrue()
        ->and($recovery->readinessAssessment->is($assessment))->toBeTrue();

    expect(fn () => DB::table('recovery_readiness_assessments')->insert([
        'recovery_episode_id' => $recovery->id,
        'assessed_by_user_id' => $nurse->id,
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => false,
        'assessed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects contradictory database state and direct mutation or deletion', function () {
    [$recovery, $nurse] = readinessAssessmentModelRecovery();

    expect(fn () => DB::table('recovery_readiness_assessments')->insert([
        'recovery_episode_id' => $recovery->id,
        'assessed_by_user_id' => $nurse->id,
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => true,
        'assessed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $assessment = RecoveryReadinessAssessment::factory()
        ->createAuthoritativeAssessmentFixture($recovery, $nurse);
    $assessment->criteria_met = true;

    expect(fn () => $assessment->save())
        ->toThrow(LogicException::class, 'authoritative Nursing workflow')
        ->and(fn () => $assessment->delete())
        ->toThrow(LogicException::class, 'cannot be deleted')
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::InProgress);
});

/** @return array{0: RecoveryEpisode, 1: User} */
function readinessAssessmentModelRecovery(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $preparation);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    return [RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedure, $nurse), $nurse];
}
