<?php

use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Nursing\CompletePreProcedureReadiness;
use App\Actions\Nursing\StartPreProcedureReadiness;
use App\Actions\Nursing\UpdatePreProcedureReadiness;
use App\Actions\Procedures\CompleteProcedureRecord;
use App\Actions\Procedures\StartProcedureRecord;
use App\Actions\Procedures\UpdateProcedureRecordDocumentation;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\PaymentMethod;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('advances the authoritative Phase 4 chain through procedure completion and stops at Nursing recovery', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $decision = $handoff->procedureDecision;
    $consultation = $decision->consultation;
    $visit = $decision->visit;
    $doctor = $decision->doctor;
    $selectedServiceId = $decision->service_catalog_item_id;
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);
    $clearance = app(GrantProcedureFinancialClearance::class)->handle($accountant, $bill);

    expect($visit->fresh()->workflowMessage())->toBe('Ready for Nursing preparation');

    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);
    app(UpdatePreProcedureReadiness::class)->handle($nurse, $readiness, [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => true,
        'medications_reviewed' => true,
        'observations' => 'Sensitive Nursing observations.',
    ]);
    app(CompletePreProcedureReadiness::class)->handle($nurse, $readiness);

    expect($readiness->fresh()->status)->toBe(PreProcedureReadinessStatus::Ready)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Doctor procedure');

    $procedureRecord = app(StartProcedureRecord::class)->handle($doctor, $visit);

    expect($procedureRecord->status)->toBe(ProcedureRecordStatus::InProgress)
        ->and($visit->fresh()->workflowMessage())->toBe('Procedure in progress');

    $procedureRecord = app(UpdateProcedureRecordDocumentation::class)->handle(
        $doctor,
        $procedureRecord,
        [
            'expected_lock_version' => 1,
            'findings' => 'Sensitive procedure findings.',
            'diagnosis_impression' => 'Sensitive diagnostic impression.',
            'specimens_taken' => true,
            'specimen_notes' => 'Sensitive specimen description.',
            'complications' => null,
            'outcome' => 'Procedure completed safely.',
            'procedure_notes' => 'Sensitive procedural notes.',
        ],
    );
    $procedureRecord = app(CompleteProcedureRecord::class)->handle(
        $doctor,
        $procedureRecord,
        $procedureRecord->lock_version,
    );

    expect($procedureRecord->status)->toBe(ProcedureRecordStatus::Completed)
        ->and($procedureRecord->completed_at)->not->toBeNull()
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Nursing recovery')
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($decision->fresh()->outcome)->toBe(ProcedureDecisionOutcome::ProcedureRequired)
        ->and($decision->fresh()->doctor_user_id)->toBe($doctor->id)
        ->and($decision->fresh()->service_catalog_item_id)->toBe($selectedServiceId)
        ->and($handoff->fresh()->procedure_decision_id)->toBe($decision->id)
        ->and($clearance->fresh()->bill_id)->toBe($bill->id)
        ->and($readiness->fresh()->nurse_user_id)->toBe($nurse->id)
        ->and($readiness->fresh()->status)->toBe(PreProcedureReadinessStatus::Ready)
        ->and(ProcedureRecord::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(Schema::hasTable('recovery_records'))->toBeFalse()
        ->and(Schema::hasTable('discharges'))->toBeFalse()
        ->and(Route::has('recovery.store'))->toBeFalse()
        ->and(Route::has('discharge.store'))->toBeFalse();

    expect(AuditLog::query()->where('action', AuditAction::ProcedureStarted)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureDocumentationUpdated)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureCompleted)->count())->toBe(1);

    foreach ([
        AuditAction::ProcedureStarted,
        AuditAction::ProcedureDocumentationUpdated,
        AuditAction::ProcedureCompleted,
    ] as $action) {
        $auditJson = json_encode(AuditLog::query()->where('action', $action)->sole()->toArray());

        expect($auditJson)
            ->not->toContain('Sensitive procedure findings.')
            ->not->toContain('Sensitive diagnostic impression.')
            ->not->toContain('Sensitive specimen description.')
            ->not->toContain('Sensitive procedural notes.')
            ->not->toContain('Sensitive Nursing observations.');
    }

    $this->actingAs($nurse)
        ->get(route('clinical.procedures.show', $procedureRecord))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinical/procedures/show')
            ->where('procedure.status.value', ProcedureRecordStatus::Completed->value)
            ->where('procedure.doctor.name', $doctor->name)
            ->where('procedure.visit.nextStep', 'Ready for Nursing recovery')
            ->where('procedure.canManage', false)
            ->where('procedure.canComplete', false)
            ->missing('procedure.bill')
            ->missing('procedure.payment')
            ->missing('procedure.financialClearance')
            ->missing('procedure.readiness.observations')
            ->missing('procedure.auditLogs'));

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.procedureDecision.procedureRecord.procedureNumber', $procedureRecord->procedure_number)
            ->where('consultation.procedureDecision.procedureRecord.status.value', ProcedureRecordStatus::Completed->value)
            ->where('consultation.visit.nextStep', 'Ready for Nursing recovery'));
});

it('does not create a procedure record for a no-procedure decision', function () {
    $decision = ProcedureDecision::factory()->createAuthoritativeDecisionFixture();
    $doctor = $decision->doctor;

    expect($decision->outcome)->toBe(ProcedureDecisionOutcome::NoProcedure)
        ->and(fn () => app(StartProcedureRecord::class)->handle(
            $doctor,
            $decision->visit,
        ))->toThrow(ValidationException::class)
        ->and(ProcedureRecord::query()->count())->toBe(0)
        ->and(AuditLog::query()->whereIn('action', [
            AuditAction::ProcedureStarted,
            AuditAction::ProcedureDocumentationUpdated,
            AuditAction::ProcedureCompleted,
        ])->count())->toBe(0);
});
