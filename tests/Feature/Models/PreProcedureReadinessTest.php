<?php

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\PreProcedureReadinessStatus;
use App\StaffRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores one sequentially referenced readiness record for its Visit and procedure decision', function () {
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $nurse,
    );

    expect($readiness->readiness_number)->toMatch('/^PPR-\d{6,}$/')
        ->and($readiness->status)->toBe(PreProcedureReadinessStatus::InPreparation)
        ->and($readiness->started_at)->not->toBeNull()
        ->and($readiness->completed_at)->toBeNull()
        ->and($readiness->visit->is($decision->visit))->toBeTrue()
        ->and($readiness->procedureDecision->is($decision))->toBeTrue()
        ->and($readiness->nurse->is($nurse))->toBeTrue();
});

it('rejects direct creation and changes to server-controlled context', function () {
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    expect(fn () => PreProcedureReadiness::query()->create([
        'visit_id' => $decision->visit_id,
        'procedure_decision_id' => $decision->id,
        'nurse_user_id' => $nurse->id,
    ]))->toThrow(LogicException::class);

    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $nurse,
    );
    $readiness->readiness_number = 'PPR-FORGED';

    expect(fn () => $readiness->save())->toThrow(LogicException::class)
        ->and($readiness->fresh()->readiness_number)->not->toBe('PPR-FORGED');
});

it('uses database constraints as duplicate, completion, and history backstops', function () {
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $nurse,
    );

    expect(fn () => DB::table('pre_procedure_readinesses')->insert([
        'visit_id' => $decision->visit_id,
        'procedure_decision_id' => $decision->id,
        'nurse_user_id' => $nurse->id,
        'readiness_number' => 'PPR-999999',
        'status' => PreProcedureReadinessStatus::InPreparation->value,
        'started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('pre_procedure_readinesses')
        ->where('id', $readiness->id)
        ->update([
            'status' => PreProcedureReadinessStatus::Ready->value,
            'completed_at' => now(),
        ]))->toThrow(QueryException::class);

    expect(fn () => $readiness->visit->delete())->toThrow(QueryException::class)
        ->and(fn () => $decision->delete())->toThrow(LogicException::class)
        ->and(fn () => $nurse->delete())->toThrow(QueryException::class);
});

it('makes completed readiness immutable and unavailable for deletion', function () {
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);

    expect(fn () => $readiness->updateFromNursingWorkflow($nurse, [
        'observations' => 'Late alteration',
    ]))->toThrow(LogicException::class)
        ->and(fn () => $readiness->delete())->toThrow(LogicException::class)
        ->and($readiness->fresh()->observations)->toBeNull();
});
