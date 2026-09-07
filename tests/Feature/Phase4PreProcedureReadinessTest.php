<?php

use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Nursing\CompletePreProcedureReadiness;
use App\Actions\Nursing\StartPreProcedureReadiness;
use App\Actions\Nursing\UpdatePreProcedureReadiness;
use App\ConsultationStatus;
use App\Models\FinancialClearance;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\User;
use App\PaymentMethod;
use App\PreProcedureReadinessStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('preserves the Doctor to Accountant to Nurse to Doctor authority chain', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $decision = $handoff->procedureDecision;
    $consultation = $decision->consultation;
    $visit = $decision->visit;
    $doctorId = $decision->doctor_user_id;
    $serviceId = $decision->service_catalog_item_id;
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);
    $clearance = app(GrantProcedureFinancialClearance::class)->handle($accountant, $bill);

    expect($visit->fresh()->workflowMessage())->toBe('Ready for Nursing preparation');

    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);
    expect($visit->fresh()->workflowMessage())->toBe('Nursing preparation in progress');

    app(UpdatePreProcedureReadiness::class)->handle($nurse, $readiness, [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => true,
        'medications_reviewed' => true,
        'observations' => null,
    ]);
    app(CompletePreProcedureReadiness::class)->handle($nurse, $readiness);

    expect($readiness->fresh()->status)->toBe(PreProcedureReadinessStatus::Ready)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Doctor procedure')
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($decision->fresh()->doctor_user_id)->toBe($doctorId)
        ->and($decision->fresh()->service_catalog_item_id)->toBe($serviceId)
        ->and($handoff->fresh()->procedure_decision_id)->toBe($decision->id)
        ->and($clearance->fresh()->bill_id)->toBe($bill->id)
        ->and(FinancialClearance::query()->where('bill_id', $bill->id)->count())->toBe(1)
        ->and(PreProcedureReadiness::query()->count())->toBe(1)
        ->and(Schema::hasTable('procedures'))->toBeFalse()
        ->and(Route::has('procedures.store'))->toBeFalse();

    $this->actingAs($decision->doctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.procedureDecision.readiness.readinessNumber', $readiness->readiness_number)
            ->where('consultation.procedureDecision.readiness.status', [
                'value' => 'ready',
                'label' => 'Ready',
            ])
            ->where('consultation.procedureDecision.readiness.nurse.name', $nurse->name)
            ->where('consultation.visit.nextStep', 'Ready for Doctor procedure')
            ->missing('consultation.procedureDecision.readiness.observations')
            ->missing('consultation.procedureDecision.readiness.nurse.email'));
});

it('does not treat consultation financial clearance as the procedure readiness gate', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $consultationClearance = FinancialClearance::factory()->create();
    $visit = $consultationClearance->bill->visit;

    expect(fn () => app(StartPreProcedureReadiness::class)->handle(
        $nurse,
        $visit,
    ))->toThrow(ValidationException::class);

    expect(PreProcedureReadiness::query()->count())->toBe(0);
});
