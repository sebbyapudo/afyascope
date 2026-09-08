<?php

use App\Actions\Procedures\CompleteProcedureRecord;
use App\Actions\Procedures\UpdateProcedureRecordDocumentation;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\PreProcedureReadinessStatus;
use App\ProcedureRecordStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('preserves completed Procedure Record as the durable recovery handoff without auto-creation', function () {
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparationNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $preparationNurse);
    $procedureRecord = ProcedureRecord::factory()->createAuthoritativeProcedureFixture(
        $decision,
        $readiness,
    );
    $procedureRecord = app(UpdateProcedureRecordDocumentation::class)->handle(
        $decision->doctor,
        $procedureRecord,
        [
            'expected_lock_version' => 1,
            'findings' => 'Documented findings.',
            'diagnosis_impression' => null,
            'specimens_taken' => false,
            'specimen_notes' => null,
            'complications' => null,
            'outcome' => 'Completed safely.',
            'procedure_notes' => null,
        ],
    );
    $auditCountBeforeCompletion = AuditLog::query()->count();

    $procedureRecord = app(CompleteProcedureRecord::class)->handle(
        $decision->doctor,
        $procedureRecord,
        $procedureRecord->lock_version,
    );

    expect($procedureRecord->status)->toBe(ProcedureRecordStatus::Completed)
        ->and($procedureRecord->isReadyForNursingRecovery())->toBeTrue()
        ->and($procedureRecord->visit->fresh()->workflowMessage())->toBe('Ready for Nursing recovery')
        ->and($procedureRecord->visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($procedureRecord->procedureDecision->is($decision))->toBeTrue()
        ->and($decision->consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($readiness->fresh()->status)->toBe(PreProcedureReadinessStatus::Ready)
        ->and(RecoveryEpisode::query()->where('procedure_record_id', $procedureRecord->id)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCountBeforeCompletion + 1)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureCompleted)->count())->toBe(1);
});

it('does not make an incomplete Procedure Record recovery-ready', function () {
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparationNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recoveryNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $preparationNurse);
    $procedureRecord = ProcedureRecord::factory()->createAuthoritativeProcedureFixture(
        $decision,
        $readiness,
    );

    expect($procedureRecord->status)->toBe(ProcedureRecordStatus::InProgress)
        ->and($procedureRecord->isReadyForNursingRecovery())->toBeFalse()
        ->and($procedureRecord->visit->fresh()->workflowMessage())->toBe('Procedure in progress')
        ->and(fn () => RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture(
            $procedureRecord,
            $recoveryNurse,
        ))->toThrow(LogicException::class, 'completed Procedure Record')
        ->and(RecoveryEpisode::query()->count())->toBe(0);
});

it('adds only the structural recovery foundation without routes UI or audit actions', function () {
    $auditActionValues = array_column(AuditAction::cases(), 'value');

    expect(Schema::hasTable('recovery_episodes'))->toBeTrue()
        ->and(Schema::hasTable('discharges'))->toBeFalse()
        ->and(Route::has('recovery.index'))->toBeFalse()
        ->and(Route::has('recovery.store'))->toBeFalse()
        ->and(Route::has('recovery.complete'))->toBeFalse()
        ->and(Route::has('discharge.store'))->toBeFalse()
        ->and($auditActionValues)->not->toContain('recovery.started', 'recovery.completed');
});
