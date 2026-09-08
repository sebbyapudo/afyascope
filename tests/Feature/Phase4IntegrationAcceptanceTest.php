<?php

use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Consultations\BeginConsultation;
use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Consultations\UpdateConsultationAssessment;
use App\Actions\Nursing\CompletePreProcedureReadiness;
use App\Actions\Nursing\StartPreProcedureReadiness;
use App\Actions\Nursing\UpdatePreProcedureReadiness;
use App\Actions\Procedures\CompleteProcedureRecord;
use App\Actions\Procedures\StartProcedureRecord;
use App\Actions\Procedures\UpdateProcedureRecordDocumentation;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\VisitCheckIn;
use App\PaymentMethod;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('accepts the complete procedure-required Phase 4 journey through Nursing recovery handoff', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $checkIn = VisitCheckIn::factory()->for($receptionist, 'checkedInBy')->create();
    $visit = $checkIn->visit;
    $procedureService = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Upper gastrointestinal endoscopy',
        'unit_price_minor' => 275_000,
    ]);
    $workflowMessages = [$visit->fresh()->workflowMessage()];

    $consultation = app(BeginConsultation::class)->handle($doctor, $visit);
    $workflowMessages[] = $visit->fresh()->workflowMessage();
    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'presenting_complaint' => 'Sensitive presenting complaint.',
        'relevant_history' => 'Sensitive relevant history.',
        'assessment_impression' => 'Sensitive clinical assessment.',
        'plan_notes' => 'Sensitive consultation plan.',
    ]);
    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $procedureService->id,
        'clinical_rationale' => 'Sensitive procedure rationale.',
        'confirmed' => true,
    ]);
    $handoff = $decision->procedureBillingHandoff;
    $workflowMessages[] = $visit->fresh()->workflowMessage();

    $procedureBill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    $workflowMessages[] = $visit->fresh()->workflowMessage();
    $receipt = app(RecordProcedurePayment::class)->handle(
        $accountant,
        $procedureBill,
        PaymentMethod::MobileMoney,
    );
    $payment = $receipt->payment;
    $workflowMessages[] = $visit->fresh()->workflowMessage();
    $clearance = app(GrantProcedureFinancialClearance::class)->handle($accountant, $procedureBill);
    $workflowMessages[] = $visit->fresh()->workflowMessage();

    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);
    $workflowMessages[] = $visit->fresh()->workflowMessage();
    app(UpdatePreProcedureReadiness::class)->handle($nurse, $readiness, [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => true,
        'medications_reviewed' => true,
        'observations' => 'Sensitive Nursing observations.',
    ]);
    $readiness = app(CompletePreProcedureReadiness::class)->handle($nurse, $readiness);
    $workflowMessages[] = $visit->fresh()->workflowMessage();

    $procedureRecord = app(StartProcedureRecord::class)->handle($doctor, $visit);
    $workflowMessages[] = $visit->fresh()->workflowMessage();
    $procedureRecord = app(UpdateProcedureRecordDocumentation::class)->handle(
        $doctor,
        $procedureRecord,
        [
            'expected_lock_version' => 1,
            'findings' => 'Sensitive procedure findings.',
            'diagnosis_impression' => 'Sensitive diagnosis impression.',
            'specimens_taken' => true,
            'specimen_notes' => 'Sensitive specimen notes.',
            'complications' => null,
            'outcome' => 'Procedure completed safely.',
            'procedure_notes' => 'Sensitive procedure notes.',
        ],
    );
    $procedureRecord = app(CompleteProcedureRecord::class)->handle(
        $doctor,
        $procedureRecord,
        $procedureRecord->lock_version,
    );
    $workflowMessages[] = $visit->fresh()->workflowMessage();

    expect($workflowMessages)->toBe([
        'Ready for Doctor consultation',
        'Consultation in progress',
        'Awaiting procedure billing',
        'Awaiting procedure payment',
        'Awaiting procedure financial clearance',
        'Ready for Nursing preparation',
        'Nursing preparation in progress',
        'Ready for Doctor procedure',
        'Procedure in progress',
        'Ready for Nursing recovery',
    ]);

    expect($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($decision->doctor_user_id)->toBe($doctor->id)
        ->and($decision->service_catalog_item_id)->toBe($procedureService->id)
        ->and($handoff->decided_by_user_id)->toBe($doctor->id)
        ->and($handoff->service_catalog_item_id)->toBe($procedureService->id)
        ->and($procedureBill->totalAmountMinor())->toBe(275_000)
        ->and($procedureBill->fresh()->status)->toBe(BillStatus::Paid)
        ->and($payment->recorded_by_user_id)->toBe($accountant->id)
        ->and($payment->amount_minor)->toBe(275_000)
        ->and($receipt->payment_id)->toBe($payment->id)
        ->and($clearance->granted_by_user_id)->toBe($accountant->id)
        ->and($readiness->nurse_user_id)->toBe($nurse->id)
        ->and($readiness->status)->toBe(PreProcedureReadinessStatus::Ready)
        ->and($procedureRecord->doctor_user_id)->toBe($doctor->id)
        ->and($procedureRecord->status)->toBe(ProcedureRecordStatus::Completed);

    expect(VisitCheckIn::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(Consultation::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(ProcedureDecision::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(ProcedureBillingHandoff::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(Bill::query()->where('visit_id', $visit->id)->where('type', BillType::Procedure)->count())->toBe(1)
        ->and(Payment::query()->where('bill_id', $procedureBill->id)->count())->toBe(1)
        ->and(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and(FinancialClearance::query()->where('bill_id', $procedureBill->id)->count())->toBe(1)
        ->and(PreProcedureReadiness::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(ProcedureRecord::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(Schema::hasTable('recovery_records'))->toBeFalse()
        ->and(Schema::hasTable('discharges'))->toBeFalse()
        ->and(Route::has('recovery.store'))->toBeFalse()
        ->and(Route::has('discharge.store'))->toBeFalse();

    $expectedAuditActions = [
        AuditAction::ConsultationStarted,
        AuditAction::ConsultationAssessmentUpdated,
        AuditAction::ConsultationProcedureDecided,
        AuditAction::BillCreated,
        AuditAction::PaymentRecorded,
        AuditAction::ReceiptIssued,
        AuditAction::ProcedureFinancialCleared,
        AuditAction::NursingPreparationStarted,
        AuditAction::NursingReadinessCompleted,
        AuditAction::ProcedureStarted,
        AuditAction::ProcedureDocumentationUpdated,
        AuditAction::ProcedureCompleted,
    ];

    expect(AuditLog::query()->count())->toBe(count($expectedAuditActions));

    foreach ($expectedAuditActions as $auditAction) {
        expect(AuditLog::query()->where('action', $auditAction)->count())->toBe(1);
    }

    $auditPayload = json_encode(AuditLog::query()->get()->toArray());

    expect($auditPayload)
        ->not->toContain('Sensitive presenting complaint.')
        ->not->toContain('Sensitive relevant history.')
        ->not->toContain('Sensitive clinical assessment.')
        ->not->toContain('Sensitive consultation plan.')
        ->not->toContain('Sensitive procedure rationale.')
        ->not->toContain('Sensitive Nursing observations.')
        ->not->toContain('Sensitive procedure findings.')
        ->not->toContain('Sensitive diagnosis impression.')
        ->not->toContain('Sensitive specimen notes.')
        ->not->toContain('Sensitive procedure notes.');

    $this->actingAs($receptionist)
        ->get(route('patients.show', $visit->patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.visitNumber', $visit->visit_number)
            ->where('visitHistory.data.0.nextStep', 'Ready for Nursing recovery'));

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.nextStep', 'Ready for Nursing recovery'));

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.visit.nextStep', 'Ready for Nursing recovery')
            ->where('consultation.procedureDecision.procedureRecord.procedureNumber', $procedureRecord->procedure_number)
            ->where('consultation.procedureDecision.procedureRecord.status.value', ProcedureRecordStatus::Completed->value));
});

it('accepts the no-procedure branch without creating downstream procedure records', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = VisitCheckIn::factory()->create()->visit;
    $consultation = app(BeginConsultation::class)->handle($doctor, $visit);
    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'assessment_impression' => 'No endoscopic procedure is indicated.',
    ]);
    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'clinical_rationale' => 'Conservative management is appropriate.',
        'confirmed' => true,
    ]);
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(StartPreProcedureReadiness::class)->handle(
        $nurse,
        $visit,
    ))->toThrow(ValidationException::class);
    expect(fn () => app(StartProcedureRecord::class)->handle(
        $doctor,
        $visit,
    ))->toThrow(ValidationException::class);

    expect($decision->outcome)->toBe(ProcedureDecisionOutcome::NoProcedure)
        ->and($decision->service_catalog_item_id)->toBeNull()
        ->and($visit->fresh()->workflowMessage())->toBe('No procedure required')
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and(ProcedureBillingHandoff::query()->where('visit_id', $visit->id)->count())->toBe(0)
        ->and(Bill::query()->where('visit_id', $visit->id)->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(Payment::query()->whereHas('bill', fn ($query) => $query
            ->where('visit_id', $visit->id)
            ->where('type', BillType::Procedure->value))->count())->toBe(0)
        ->and(Receipt::query()->whereHas('payment.bill', fn ($query) => $query
            ->where('visit_id', $visit->id)
            ->where('type', BillType::Procedure->value))->count())->toBe(0)
        ->and(FinancialClearance::query()->whereHas('bill', fn ($query) => $query
            ->where('visit_id', $visit->id)
            ->where('type', BillType::Procedure->value))->count())->toBe(0)
        ->and(PreProcedureReadiness::query()->where('visit_id', $visit->id)->count())->toBe(0)
        ->and(ProcedureRecord::query()->where('visit_id', $visit->id)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('treats each durable handoff as authoritative without reopening unrelated upstream internals', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $checkIn = VisitCheckIn::factory()->create();
    $visit = $checkIn->visit;
    $consultationBill = $visit->consultationBill;

    DB::table('bills')->where('id', $consultationBill->id)->update([
        'status' => BillStatus::Open->value,
    ]);

    $consultation = app(BeginConsultation::class)->handle($doctor, $visit);
    $procedureService = ServiceCatalogItem::factory()->procedure()->create();
    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $procedureService->id,
        'confirmed' => true,
    ]);
    $procedureBill = app(CreateProcedureBill::class)->handle(
        $accountant,
        $decision->procedureBillingHandoff,
    );

    expect(fn () => app(StartPreProcedureReadiness::class)->handle(
        $nurse,
        $visit,
    ))->toThrow(ValidationException::class);

    app(RecordProcedurePayment::class)->handle($accountant, $procedureBill, PaymentMethod::Cash);

    expect(fn () => app(StartPreProcedureReadiness::class)->handle(
        $nurse,
        $visit,
    ))->toThrow(ValidationException::class);

    app(GrantProcedureFinancialClearance::class)->handle($accountant, $procedureBill);
    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);

    expect(fn () => app(StartProcedureRecord::class)->handle(
        $doctor,
        $visit,
    ))->toThrow(ValidationException::class);

    app(UpdatePreProcedureReadiness::class)->handle($nurse, $readiness, [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => true,
        'medications_reviewed' => true,
        'observations' => null,
    ]);
    $readiness = app(CompletePreProcedureReadiness::class)->handle($nurse, $readiness);

    DB::table('bills')->where('id', $procedureBill->id)->update([
        'status' => BillStatus::Open->value,
    ]);

    $procedureRecord = app(StartProcedureRecord::class)->handle($doctor, $visit);
    $procedureRecord = app(UpdateProcedureRecordDocumentation::class)->handle(
        $doctor,
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
    $procedureRecord = app(CompleteProcedureRecord::class)->handle(
        $doctor,
        $procedureRecord,
        $procedureRecord->lock_version,
    );

    expect($consultation->visit->is($visit))->toBeTrue()
        ->and($decision->procedureBillingHandoff)->toBeInstanceOf(ProcedureBillingHandoff::class)
        ->and($readiness->status)->toBe(PreProcedureReadinessStatus::Ready)
        ->and($procedureRecord->status)->toBe(ProcedureRecordStatus::Completed)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Nursing recovery');
});
