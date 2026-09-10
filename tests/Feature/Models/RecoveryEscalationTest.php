<?php

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\User;
use App\RecoveryEscalationResolution;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores attributable escalation context and enforces one open escalation per recovery', function () {
    [$recovery, $nurse] = recoveryEscalationModelRecovery();
    $escalation = RecoveryEscalation::factory()->createAuthoritativeEscalationFixture($recovery, $nurse);

    expect(Schema::hasColumns('recovery_escalations', [
        'recovery_episode_id',
        'escalated_by_user_id',
        'reason',
        'status',
        'open_marker',
        'escalated_at',
        'resolved_by_user_id',
        'resolution',
        'resolution_note',
        'resolved_at',
    ]))->toBeTrue()
        ->and($escalation->recoveryEpisode->is($recovery))->toBeTrue()
        ->and($escalation->escalatedBy->is($nurse))->toBeTrue()
        ->and($recovery->openEscalation->is($escalation))->toBeTrue();

    expect(fn () => DB::table('recovery_escalations')->insert(recoveryEscalationRow([
        'recovery_episode_id' => $recovery->id,
        'escalated_by_user_id' => $nurse->id,
    ])))->toThrow(QueryException::class);
});

it('allows a later escalation only after the prior escalation is durably resolved', function () {
    [$recovery, $nurse] = recoveryEscalationModelRecovery();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $first = RecoveryEscalation::factory()->createAuthoritativeEscalationFixture($recovery, $nurse);
    $first->resolveFromClinicalWorkflow($doctor, RecoveryEscalationResolution::ContinueMonitoring, null);
    $second = RecoveryEscalation::factory()->createAuthoritativeEscalationFixture($recovery, $nurse);

    expect($first->fresh()->open_marker)->toBeNull()
        ->and($second->open_marker)->toBeTrue()
        ->and(RecoveryEscalation::query()->count())->toBe(2)
        ->and(RecoveryEscalation::query()->where('open_marker', true)->count())->toBe(1);
});

it('rejects direct escalation mutation and deletion', function () {
    [$recovery, $nurse] = recoveryEscalationModelRecovery();
    $escalation = RecoveryEscalation::factory()->createAuthoritativeEscalationFixture($recovery, $nurse);
    $escalation->reason = 'Forged change';

    expect(fn () => $escalation->save())
        ->toThrow(LogicException::class, 'authoritative workflow actions')
        ->and(fn () => $escalation->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

/** @param array<string, mixed> $overrides */
function recoveryEscalationRow(array $overrides): array
{
    return array_replace([
        'reason' => 'Doctor review required.',
        'status' => 'open',
        'open_marker' => true,
        'escalated_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

/** @return array{0: RecoveryEpisode, 1: User} */
function recoveryEscalationModelRecovery(): array
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
