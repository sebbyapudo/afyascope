<?php

use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Nursing\CompletePreProcedureReadiness;
use App\Actions\Nursing\StartPreProcedureReadiness;
use App\Actions\Nursing\UpdatePreProcedureReadiness;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\Models\Visit;
use App\PaymentMethod;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only financially cleared procedure-required Visits awaiting Nurse readiness oldest first', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    $this->travelTo('2026-09-06 08:00:00');
    $oldest = readinessControllerClearedVisit();
    $this->travelTo('2026-09-06 09:00:00');
    $inProgress = readinessControllerClearedVisit();
    app(StartPreProcedureReadiness::class)->handle($nurse, $inProgress);
    $this->travelTo('2026-09-06 10:00:00');
    $ready = readinessControllerClearedVisit();
    $readyPreparation = app(StartPreProcedureReadiness::class)->handle($nurse, $ready);
    app(UpdatePreProcedureReadiness::class)->handle(
        $nurse,
        $readyPreparation,
        readinessControllerCompleteChecks(),
    );
    app(CompletePreProcedureReadiness::class)->handle(
        $nurse,
        $readyPreparation,
    );
    $paidUncleared = readinessControllerProcedureVisit(false);
    ProcedureDecision::factory()->createAuthoritativeDecisionFixture();
    $this->travelBack();

    $this->actingAs($nurse)
        ->get(route('nursing.pre-procedure-readiness.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('nursing/pre-procedure-readiness/index')
            ->where('preparations.data', fn ($items): bool => collect($items)
                ->pluck('visit.id')->all() === [$oldest->id, $inProgress->id])
            ->where('preparations.pagination.total', 2)
            ->where('preparations.data.0.visit.nextStep', 'Ready for Nursing preparation')
            ->where('preparations.data.0.readiness', null)
            ->where('preparations.data.1.visit.nextStep', 'Nursing preparation in progress')
            ->where('preparations.data.1.readiness.canManage', true)
            ->missing('preparations.data.0.bill')
            ->missing('preparations.data.0.payment')
            ->missing('preparations.data.0.receipt')
            ->missing('preparations.data.0.financialClearance')
            ->missing('preparations.data.0.price')
            ->missing('preparations.data.0.auditLogs')
            ->missing('preparations.data.0.doctor.email'));

    expect(collect([$ready->id, $paidUncleared->id])->intersect([$oldest->id, $inProgress->id]))->toBeEmpty();
});

it('starts preparation and shows a sanitized Nurse workspace', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = readinessControllerClearedVisit();
    $visit->consultation->current_medications = 'Reviewed medication context.';
    $visit->consultation->allergies = 'Reviewed allergy context.';
    $visit->consultation->save();

    $response = $this->actingAs($nurse)
        ->post(route('nursing.pre-procedure-readiness.store', $visit));
    $readiness = PreProcedureReadiness::query()->sole();

    $response
        ->assertRedirect(route('nursing.pre-procedure-readiness.show', $readiness))
        ->assertSessionHas('status', "Nursing preparation {$readiness->readiness_number} was started.");

    $this->actingAs($nurse)
        ->get(route('nursing.pre-procedure-readiness.show', $readiness))
        ->assertInertia(fn (Assert $page) => $page
            ->component('nursing/pre-procedure-readiness/show')
            ->where('readiness.readinessNumber', $readiness->readiness_number)
            ->where('readiness.nurse.name', $nurse->name)
            ->where('readiness.doctor.name', $visit->consultation->doctor->name)
            ->where('readiness.procedure.name', $visit->procedureDecision->serviceCatalogItem->name)
            ->where('readiness.patient.patientNumber', $visit->patient->patient_number)
            ->where('readiness.clinicalContext.allergies', 'Reviewed allergy context.')
            ->where('readiness.clinicalContext.currentMedications', 'Reviewed medication context.')
            ->where('readiness.canManage', true)
            ->where('readiness.canComplete', true)
            ->missing('readiness.bill')
            ->missing('readiness.payment')
            ->missing('readiness.receipt')
            ->missing('readiness.financialClearance')
            ->missing('readiness.procedure.price')
            ->missing('readiness.doctor.email')
            ->missing('readiness.nurse.email')
            ->missing('readiness.auditLogs'));
});

it('updates and completes preparation through the responsible Nurse workspace', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = readinessControllerClearedVisit();
    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);

    $this->actingAs($nurse)
        ->put(route('nursing.pre-procedure-readiness.update', $readiness), [
            ...readinessControllerCompleteChecks(),
            'observations' => '  Preparation complete.  ',
        ])
        ->assertRedirect(route('nursing.pre-procedure-readiness.show', $readiness))
        ->assertSessionHas('status', 'Pre-procedure readiness checks were saved.');

    $this->actingAs($nurse)
        ->post(route('nursing.pre-procedure-readiness.complete', $readiness))
        ->assertRedirect(route('nursing.pre-procedure-readiness.show', $readiness));

    expect($readiness->fresh()->status->value)->toBe('ready')
        ->and($readiness->fresh()->observations)->toBe('Preparation complete.')
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Doctor procedure')
        ->and(AuditLog::query()->where('action', AuditAction::NursingReadinessCompleted)->count())->toBe(1);
});

it('rejects forged ownership lifecycle and upstream fields from normal requests', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = readinessControllerClearedVisit();
    $forged = [
        'nurse_user_id' => 999_999,
        'readiness_number' => 'PPR-FORGED',
        'status' => 'ready',
        'started_at' => '2020-01-01 00:00:00',
        'completed_at' => '2020-01-01 01:00:00',
        'visit_id' => 999_999,
        'procedure_decision_id' => 999_999,
        'procedure_billing_handoff_id' => 999_999,
        'financial_clearance_id' => 999_999,
    ];

    $this->actingAs($nurse)
        ->post(route('nursing.pre-procedure-readiness.store', $visit), $forged)
        ->assertSessionHasErrors(array_keys($forged));

    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);

    $this->actingAs($nurse)
        ->put(route('nursing.pre-procedure-readiness.update', $readiness), [
            ...readinessControllerCompleteChecks(),
            ...$forged,
        ])
        ->assertSessionHasErrors(array_keys($forged));

    expect($readiness->fresh()->status->value)->toBe('in_preparation')
        ->and($readiness->fresh()->nurse_user_id)->toBe($nurse->id);
});

it('allows another Nurse read-only visibility but denies takeover and completion', function () {
    $responsibleNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = readinessControllerClearedVisit();
    $readiness = app(StartPreProcedureReadiness::class)->handle($responsibleNurse, $visit);

    $this->actingAs($otherNurse)
        ->get(route('nursing.pre-procedure-readiness.show', $readiness))
        ->assertInertia(fn (Assert $page) => $page
            ->where('readiness.canManage', false)
            ->where('readiness.canComplete', false)
            ->where('readiness.nurse.name', $responsibleNurse->name));

    $this->actingAs($otherNurse)
        ->put(route('nursing.pre-procedure-readiness.update', $readiness), readinessControllerCompleteChecks())
        ->assertForbidden();
    $this->actingAs($otherNurse)
        ->post(route('nursing.pre-procedure-readiness.complete', $readiness))
        ->assertForbidden();
});

it('protects all preparation endpoints from guests and non-Nurse roles', function (StaffRole $role) {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $visit = readinessControllerClearedVisit();
    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);

    $this->get(route('nursing.pre-procedure-readiness.index'))->assertRedirect(route('login'));
    $this->post(route('nursing.pre-procedure-readiness.store', $visit))->assertRedirect(route('login'));
    $this->get(route('nursing.pre-procedure-readiness.show', $readiness))->assertRedirect(route('login'));
    $this->put(route('nursing.pre-procedure-readiness.update', $readiness))->assertRedirect(route('login'));
    $this->post(route('nursing.pre-procedure-readiness.complete', $readiness))->assertRedirect(route('login'));

    $actor = User::factory()->forRole($role)->create();

    $this->actingAs($actor)->get(route('nursing.pre-procedure-readiness.index'))->assertForbidden();
    $this->actingAs($actor)->post(route('nursing.pre-procedure-readiness.store', $visit))->assertForbidden();
    $this->actingAs($actor)->get(route('nursing.pre-procedure-readiness.show', $readiness))->assertForbidden();
    $this->actingAs($actor)->put(route('nursing.pre-procedure-readiness.update', $readiness))->assertForbidden();
    $this->actingAs($actor)->post(route('nursing.pre-procedure-readiness.complete', $readiness))->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('logs out an inactive Nurse before any preparation action', function () {
    $inactiveNurse = User::factory()->forRole(StaffRole::Nurse)->inactive()->create();
    $visit = readinessControllerClearedVisit();

    $this->actingAs($inactiveNurse)
        ->post(route('nursing.pre-procedure-readiness.store', $visit))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(PreProcedureReadiness::query()->count())->toBe(0);
});

it('exposes no deletion or procedure-performance route', function () {
    expect(Route::has('nursing.pre-procedure-readiness.index'))->toBeTrue()
        ->and(Route::has('nursing.pre-procedure-readiness.store'))->toBeTrue()
        ->and(Route::has('nursing.pre-procedure-readiness.show'))->toBeTrue()
        ->and(Route::has('nursing.pre-procedure-readiness.update'))->toBeTrue()
        ->and(Route::has('nursing.pre-procedure-readiness.complete'))->toBeTrue()
        ->and(Route::has('nursing.pre-procedure-readiness.destroy'))->toBeFalse()
        ->and(Route::has('procedures.store'))->toBeFalse();
});

function readinessControllerClearedVisit(): Visit
{
    return readinessControllerProcedureVisit(true);
}

function readinessControllerProcedureVisit(bool $clear): Visit
{
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);

    if ($clear) {
        app(GrantProcedureFinancialClearance::class)->handle($accountant, $bill);
    }

    return $handoff->visit->fresh([
        'patient',
        'consultation.doctor',
        'procedureDecision.serviceCatalogItem',
    ]);
}

/** @return array<string, bool> */
function readinessControllerCompleteChecks(): array
{
    return [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => true,
        'medications_reviewed' => true,
    ];
}
