<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Visits\CompleteVisit;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\RecoveryDischarge;
use App\Models\User;
use App\Models\Visit;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;

it('completes the no-procedure Visit from its authoritative Doctor decision', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $this->travelTo('2026-09-11 10:15:00');

    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'confirmed' => true,
    ]);

    $visit = $consultation->visit->fresh();
    $finalizedConsultation = $consultation->fresh();
    $completionAudit = AuditLog::query()->where('action', AuditAction::VisitCompleted)->sole();

    expect($visit->status)->toBe(VisitStatus::Completed)
        ->and($visit->completed_at?->equalTo($decision->decided_at))->toBeTrue()
        ->and($finalizedConsultation->status)->toBe(ConsultationStatus::Finalized)
        ->and($finalizedConsultation->finalized_at?->equalTo($decision->decided_at))->toBeTrue()
        ->and($completionAudit->actor->is($doctor))->toBeTrue()
        ->and($completionAudit->subject->is($visit))->toBeTrue()
        ->and($completionAudit->before_values)->toBeNull()
        ->and($completionAudit->after_values)->toHaveCount(7)
        ->and($completionAudit->after_values)->toMatchArray([
            'visit_id' => $visit->id,
            'visit_number' => $visit->visit_number,
            'patient_id' => $visit->patient_id,
            'completion_source_type' => 'no_procedure_decision',
            'completion_source_id' => $decision->id,
            'completion_source_reference' => $decision->decision_number,
            'completed_at' => $visit->completed_at?->toIso8601String(),
        ]);
});

it('is idempotent for the same authoritative handoff and writes one completion audit', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'confirmed' => true,
    ]);

    $visit = app(CompleteVisit::class)->afterNoProcedureDecision($doctor, $decision);

    expect($visit->status)->toBe(VisitStatus::Completed)
        ->and(AuditLog::query()->where('action', AuditAction::VisitCompleted)->count())->toBe(1);
});

it('does not allow a checked-in Visit to be completed without its matching terminal clinical handoff', function () {
    $decision = ProcedureDecision::factory()->createAuthoritativeDecisionFixture();
    $unrelatedVisit = Consultation::factory()->create()->visit;

    expect(fn () => $unrelatedVisit->completeFromClinicalWorkflow($decision, $decision->decided_at))
        ->toThrow(LogicException::class, 'authoritative terminal clinical handoff');

    expect($unrelatedVisit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($unrelatedVisit->fresh()->completed_at)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::VisitCompleted)->count())->toBe(0);
});

it('does not complete a procedure Visit before a finalized Nursing discharge exists', function () {
    $decision = ProcedureDecision::factory()
        ->procedureRequired()
        ->createAuthoritativeDecisionFixture();
    $visit = $decision->visit;

    expect(fn () => $visit->completeFromClinicalWorkflow(new RecoveryDischarge, now()->toImmutable()))
        ->toThrow(LogicException::class, 'authoritative terminal clinical handoff');

    expect($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->completed_at)->toBeNull();
});

it('makes completed Visits terminal and keeps completion fields server-controlled', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'confirmed' => true,
    ]);
    $visit = $consultation->visit->fresh();

    $visit->occurred_at = $visit->occurred_at->addMinute();

    expect(fn () => $visit->save())
        ->toThrow(LogicException::class, 'Completed Visits cannot be changed.');

    $forgedVisit = Visit::factory()->create([
        'status' => VisitStatus::Completed,
        'completed_at' => now()->subDay(),
    ]);

    expect($forgedVisit->status)->toBe(VisitStatus::Created)
        ->and($forgedVisit->completed_at)->toBeNull();
});

it('rolls back the decision consultation and Visit when completion audit recording fails', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $auditRecorder = Mockery::mock(RecordAuditLog::class);
    $auditRecorder->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $actor, AuditAction $action): bool => $actor->is($doctor)
            && $action === AuditAction::ConsultationProcedureDecided)
        ->andReturn(new AuditLog)
        ->ordered();
    $auditRecorder->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $actor, AuditAction $action): bool => $actor->is($doctor)
            && $action === AuditAction::VisitCompleted)
        ->andThrow(new RuntimeException('Visit completion audit failed.'))
        ->ordered();
    $completeVisit = new CompleteVisit($auditRecorder);

    expect(fn () => (new RecordProcedureDecision($auditRecorder, $completeVisit))->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'confirmed' => true,
    ]))->toThrow(RuntimeException::class, 'Visit completion audit failed.');

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->fresh()->finalized_at)->toBeNull()
        ->and($consultation->visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($consultation->visit->fresh()->completed_at)->toBeNull();
});

it('has no generic manual Visit completion route', function () {
    $completionRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains((string) $route->getName(), 'complete')
            && str_contains($route->uri(), 'visit'));

    expect($completionRoutes)->toBeEmpty()
        ->and(DB::table('permissions')->where('slug', 'visits.complete')->exists())->toBeFalse();
});
