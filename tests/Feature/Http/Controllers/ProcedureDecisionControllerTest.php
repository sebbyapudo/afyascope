<?php

use App\Actions\Consultations\RecordProcedureDecision;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('projects only active procedure services to the responsible Doctors workspace', function () {
    $doctor = procedureDecisionControllerDoctor();
    $consultation = procedureDecisionControllerConsultation($doctor);
    $activeProcedure = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Upper gastrointestinal endoscopy',
        'unit_price_minor' => 325_000,
    ]);
    ServiceCatalogItem::factory()->procedure()->inactive()->create([
        'name' => 'Inactive procedure',
    ]);
    ServiceCatalogItem::factory()->create(['name' => 'Consultation']);

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinical/consultations/show')
            ->where('consultation.canManage', true)
            ->where('consultation.canRecordProcedureDecision', true)
            ->where('consultation.procedureDecision', null)
            ->where('consultation.visit.nextStep', 'Consultation in progress')
            ->where('procedureServices', [[
                'id' => $activeProcedure->id,
                'name' => 'Upper gastrointestinal endoscopy',
            ]])
            ->missing('procedureServices.0.unit_price_minor')
            ->missing('procedureServices.0.unitPriceMinor')
            ->missing('procedureServices.0.category')
            ->missing('consultation.visit.bill')
            ->missing('consultation.visit.payment')
            ->missing('consultation.visit.financialClearance')
        );
});

it('records procedure-required through the workspace and then projects it read-only', function () {
    $doctor = procedureDecisionControllerDoctor();
    $consultation = procedureDecisionControllerConsultation($doctor);
    $service = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Colonoscopy',
    ]);

    $response = $this->actingAs($doctor)
        ->post(route('clinical.consultations.procedure-decision.store', $consultation), [
            'outcome' => 'procedure_required',
            'service_catalog_item_id' => $service->id,
            'clinical_rationale' => 'Procedure is clinically indicated.',
            'confirmed' => '1',
        ]);
    $decision = ProcedureDecision::query()->sole();
    $handoff = ProcedureBillingHandoff::query()->sole();

    $response
        ->assertRedirect(route('clinical.consultations.show', $consultation))
        ->assertSessionHas('status', "Procedure decision {$decision->decision_number} was recorded.");

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.canManage', true)
            ->where('consultation.canRecordProcedureDecision', false)
            ->where('procedureServices', [])
            ->where('consultation.procedureDecision.decisionNumber', $decision->decision_number)
            ->where('consultation.procedureDecision.outcome', [
                'value' => 'procedure_required',
                'label' => 'Procedure required',
            ])
            ->where('consultation.procedureDecision.clinicalRationale', 'Procedure is clinically indicated.')
            ->where('consultation.procedureDecision.service', [
                'id' => $service->id,
                'name' => 'Colonoscopy',
            ])
            ->where('consultation.procedureDecision.handoff.handoffNumber', $handoff->handoff_number)
            ->where('consultation.visit.nextStep', 'Awaiting procedure billing')
            ->missing('consultation.procedureDecision.doctor_user_id')
            ->missing('consultation.procedureDecision.service.unit_price_minor')
            ->missing('consultation.procedureDecision.service.unitPriceMinor')
            ->missing('consultation.procedureDecision.bill')
        );

    expect(Bill::query()->where('type', 'procedure')->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationProcedureDecided)->count())->toBe(1);
});

it('records and projects no-procedure without exposing a billing handoff', function () {
    $doctor = procedureDecisionControllerDoctor();
    $consultation = procedureDecisionControllerConsultation($doctor);

    $this->actingAs($doctor)
        ->post(route('clinical.consultations.procedure-decision.store', $consultation), [
            'outcome' => 'no_procedure',
            'clinical_rationale' => '',
            'confirmed' => true,
        ])
        ->assertRedirect(route('clinical.consultations.show', $consultation));

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.procedureDecision.outcome', [
                'value' => 'no_procedure',
                'label' => 'No procedure required',
            ])
            ->where('consultation.procedureDecision.service', null)
            ->where('consultation.procedureDecision.handoff', null)
            ->where('consultation.visit.nextStep', 'No procedure required')
            ->where('procedureServices', [])
        );

    expect(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(Bill::query()->where('type', 'procedure')->count())->toBe(0);
});

it('keeps the decision workspace read-only for another Doctor', function () {
    $responsibleDoctor = procedureDecisionControllerDoctor();
    $otherDoctor = procedureDecisionControllerDoctor();
    $consultation = procedureDecisionControllerConsultation($responsibleDoctor);
    app(RecordProcedureDecision::class)->handle($responsibleDoctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'confirmed' => true,
    ]);

    $this->actingAs($otherDoctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.canManage', false)
            ->where('consultation.canRecordProcedureDecision', false)
            ->where('procedureServices', [])
            ->where('consultation.doctor.name', $responsibleDoctor->name)
            ->where('consultation.procedureDecision.outcome.value', 'no_procedure')
        );

    $this->actingAs($otherDoctor)
        ->post(route('clinical.consultations.procedure-decision.store', $consultation), [
            'outcome' => 'no_procedure',
            'confirmed' => true,
        ])
        ->assertForbidden();

    expect(ProcedureDecision::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationProcedureDecided)->count())->toBe(1);
});

it('projects the new workflow without exposing the clinical decision to Reception', function () {
    $doctor = procedureDecisionControllerDoctor();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $consultation = procedureDecisionControllerConsultation($doctor);
    $visit = $consultation->visit;
    $patient = $visit->patient;
    $service = ServiceCatalogItem::factory()->procedure()->create();
    app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => 'procedure_required',
        'service_catalog_item_id' => $service->id,
        'confirmed' => true,
    ]);

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.nextStep', 'Awaiting procedure billing')
            ->missing('visit.procedureDecision')
            ->missing('visit.procedureBillingHandoff')
        );

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.nextStep', 'Awaiting procedure billing')
            ->missing('visitHistory.data.0.procedureDecision')
            ->missing('visitHistory.data.0.procedureBillingHandoff')
        );
});

it('rejects forged server-controlled and financial fields through the endpoint', function () {
    $doctor = procedureDecisionControllerDoctor();
    $consultation = procedureDecisionControllerConsultation($doctor);
    $service = ServiceCatalogItem::factory()->procedure()->create();
    $forged = [
        'id' => 999_999,
        'consultation_id' => 999_999,
        'visit_id' => 999_999,
        'patient_id' => 999_999,
        'doctor_id' => 999_999,
        'doctor_user_id' => 999_999,
        'decision_number' => 'PDC-FORGED',
        'decided_at' => '2020-01-01 00:00:00',
        'procedure_billing_handoff_id' => 999_999,
        'handoff_number' => 'PBH-FORGED',
        'bill_id' => 999_999,
        'amount_minor' => 1,
        'unit_price_minor' => 1,
        'price' => 1,
    ];

    $this->actingAs($doctor)
        ->post(route('clinical.consultations.procedure-decision.store', $consultation), [
            ...$forged,
            'outcome' => 'procedure_required',
            'service_catalog_item_id' => $service->id,
            'confirmed' => true,
        ])
        ->assertSessionHasErrors(array_keys($forged));

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects a procedure-required request without service or confirmation', function () {
    $doctor = procedureDecisionControllerDoctor();
    $consultation = procedureDecisionControllerConsultation($doctor);

    $this->actingAs($doctor)
        ->post(route('clinical.consultations.procedure-decision.store', $consultation), [
            'outcome' => 'procedure_required',
        ])
        ->assertSessionHasErrors(['service_catalog_item_id', 'confirmed']);

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('redirects guests and denies every non-Doctor role from the decision endpoint', function (StaffRole $role) {
    $consultation = procedureDecisionControllerConsultation(procedureDecisionControllerDoctor());
    $payload = ['outcome' => 'no_procedure', 'confirmed' => true];

    $this->post(route('clinical.consultations.procedure-decision.store', $consultation), $payload)
        ->assertRedirect(route('login'));

    $actor = User::factory()->forRole($role)->create();

    $this->actingAs($actor)
        ->post(route('clinical.consultations.procedure-decision.store', $consultation), $payload)
        ->assertForbidden();

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('logs out an inactive responsible Doctor before recording a decision', function () {
    $doctor = procedureDecisionControllerDoctor();
    $consultation = procedureDecisionControllerConsultation($doctor);
    DB::table('users')->where('id', $doctor->id)->update(['is_active' => false]);
    $doctor->refresh();

    $this->actingAs($doctor)
        ->post(route('clinical.consultations.procedure-decision.store', $consultation), [
            'outcome' => 'no_procedure',
            'confirmed' => true,
        ])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('adds only the decision endpoint and no procedure billing or execution routes', function () {
    expect(Route::has('clinical.consultations.procedure-decision.store'))->toBeTrue()
        ->and(Route::has('billing.procedures.index'))->toBeTrue()
        ->and(Route::has('billing.procedures.store'))->toBeTrue()
        ->and(Route::has('clinical.consultations.finalize'))->toBeFalse()
        ->and(Route::has('clinical.procedures.perform'))->toBeFalse();
});

function procedureDecisionControllerDoctor(): User
{
    return User::factory()->forRole(StaffRole::Doctor)->create();
}

function procedureDecisionControllerConsultation(User $doctor): Consultation
{
    return Consultation::factory()->for($doctor, 'doctor')->create();
}
