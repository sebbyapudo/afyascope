<?php

use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Procedures\StartProcedureRecord;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\PaymentMethod;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only the responsible Doctor ready and in-progress procedure work oldest first', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();

    $this->travelTo('2026-09-08 08:00:00');
    [$oldestDecision] = procedureControllerReadyContext($doctor);
    $this->travelTo('2026-09-08 09:00:00');
    [$newerDecision] = procedureControllerReadyContext($doctor);
    $this->travelTo('2026-09-08 10:00:00');
    [$inProgressDecision] = procedureControllerReadyContext($doctor);
    $inProgress = app(StartProcedureRecord::class)->handle($doctor, $inProgressDecision->visit);
    procedureControllerReadyContext($otherDoctor);

    $inPreparationDecision = procedureControllerProcedureDecision($doctor);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $inPreparationDecision,
        $nurse,
    );
    ProcedureDecision::factory()
        ->for(Consultation::factory()->for($doctor, 'doctor'))
        ->createAuthoritativeDecisionFixture();
    $financiallyClearedHandoff = ProcedureBillingHandoff::factory()
        ->createAuthoritativeDecisionFixture([
            'decided_by_user_id' => $doctor->id,
        ]);
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $financiallyClearedBill = app(CreateProcedureBill::class)->handle(
        $accountant,
        $financiallyClearedHandoff,
    );
    app(RecordProcedurePayment::class)->handle(
        $accountant,
        $financiallyClearedBill,
        PaymentMethod::Cash,
    );
    app(GrantProcedureFinancialClearance::class)->handle(
        $accountant,
        $financiallyClearedBill,
    );
    $this->travelBack();

    $this->actingAs($doctor)
        ->get(route('clinical.procedures.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinical/procedures/index')
            ->where('readyProcedures.data', fn ($items): bool => collect($items)
                ->pluck('visit.id')->all() === [$oldestDecision->visit_id, $newerDecision->visit_id])
            ->where('readyProcedures.pagination.pageName', 'ready_page')
            ->where('readyProcedures.pagination.total', 2)
            ->where('readyProcedures.data.0.visit.nextStep', 'Ready for Doctor procedure')
            ->where('inProgressProcedures.data.0.id', $inProgress->id)
            ->where('inProgressProcedures.data.0.visit.nextStep', 'Procedure in progress')
            ->where('inProgressProcedures.pagination.pageName', 'in_progress_page')
            ->where('inProgressProcedures.pagination.total', 1)
            ->missing('readyProcedures.data.0.bill')
            ->missing('readyProcedures.data.0.payment')
            ->missing('readyProcedures.data.0.receipt')
            ->missing('readyProcedures.data.0.financialClearance')
            ->missing('readyProcedures.data.0.price')
            ->missing('readyProcedures.data.0.readiness.observations')
            ->missing('readyProcedures.data.0.doctor.email'));
});

it('starts a procedure and renders a sanitized Doctor workspace', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    [$decision, $readiness] = procedureControllerReadyContext($doctor);

    $response = $this->actingAs($doctor)
        ->post(route('clinical.procedures.store', $decision->visit));
    $procedureRecord = ProcedureRecord::query()->sole();

    $response
        ->assertRedirect(route('clinical.procedures.show', $procedureRecord))
        ->assertSessionHas('status', "Procedure {$procedureRecord->procedure_number} was started.");

    $this->actingAs($doctor)
        ->get(route('clinical.procedures.show', $procedureRecord))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinical/procedures/show')
            ->where('procedure.procedureNumber', $procedureRecord->procedure_number)
            ->where('procedure.doctor.name', $doctor->name)
            ->where('procedure.patient.patientNumber', $decision->visit->patient->patient_number)
            ->where('procedure.selectedProcedure.name', $decision->serviceCatalogItem->name)
            ->where('procedure.selectedProcedure.decisionNumber', $decision->decision_number)
            ->where('procedure.readiness.readinessNumber', $readiness->readiness_number)
            ->where('procedure.readiness.nurse.name', $readiness->nurse->name)
            ->where('procedure.visit.nextStep', 'Procedure in progress')
            ->where('procedure.lockVersion', 1)
            ->where('procedure.canManage', true)
            ->where('procedure.canComplete', true)
            ->missing('procedure.bill')
            ->missing('procedure.payment')
            ->missing('procedure.receipt')
            ->missing('procedure.financialClearance')
            ->missing('procedure.price')
            ->missing('procedure.readiness.observations')
            ->missing('procedure.doctor.email')
            ->missing('procedure.readiness.nurse.email')
            ->missing('procedure.auditLogs'));
});

it('updates and completes procedure documentation through the responsible Doctor workspace', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    [$decision] = procedureControllerReadyContext($doctor);
    $procedureRecord = app(StartProcedureRecord::class)->handle($doctor, $decision->visit);

    $this->actingAs($doctor)
        ->put(route('clinical.procedures.update', $procedureRecord), procedureControllerDocumentation())
        ->assertRedirect(route('clinical.procedures.show', $procedureRecord))
        ->assertSessionHas('status', 'Procedure documentation was saved.');

    $this->actingAs($doctor)
        ->post(route('clinical.procedures.complete', $procedureRecord), [
            'expected_lock_version' => 2,
        ])
        ->assertRedirect(route('clinical.procedures.show', $procedureRecord))
        ->assertSessionHas(
            'status',
            "Procedure {$procedureRecord->procedure_number} was completed. The patient is ready for Nursing recovery.",
        );

    expect($procedureRecord->fresh()->status->value)->toBe('completed')
        ->and($procedureRecord->fresh()->findings)->toBe('Documented findings.')
        ->and($decision->visit->fresh()->workflowMessage())->toBe('Ready for Nursing recovery')
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureCompleted)->count())->toBe(1);
});

it('rejects forged procedure ownership context lifecycle and timestamps', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    [$decision] = procedureControllerReadyContext($doctor);
    $forged = [
        'doctor_user_id' => 999_999,
        'procedure_number' => 'PRC-FORGED',
        'status' => 'completed',
        'started_at' => '2020-01-01 00:00:00',
        'completed_at' => '2020-01-01 01:00:00',
        'visit_id' => 999_999,
        'procedure_decision_id' => 999_999,
        'pre_procedure_readiness_id' => 999_999,
        'service_catalog_item_id' => 999_999,
        'lock_version' => 99,
    ];

    $this->actingAs($doctor)
        ->post(route('clinical.procedures.store', $decision->visit), $forged)
        ->assertSessionHasErrors(array_keys($forged));

    $procedureRecord = app(StartProcedureRecord::class)->handle($doctor, $decision->visit);

    $this->actingAs($doctor)
        ->put(route('clinical.procedures.update', $procedureRecord), [
            ...procedureControllerDocumentation(),
            ...$forged,
        ])
        ->assertSessionHasErrors(array_keys($forged));

    expect($procedureRecord->fresh()->status->value)->toBe('in_progress')
        ->and($procedureRecord->fresh()->doctor_user_id)->toBe($doctor->id)
        ->and($procedureRecord->fresh()->lock_version)->toBe(1);
});

it('allows another Doctor and Nurse read-only context while denying procedure actions', function () {
    $responsibleDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    [$decision] = procedureControllerReadyContext($responsibleDoctor);
    $procedureRecord = app(StartProcedureRecord::class)->handle($responsibleDoctor, $decision->visit);

    foreach ([$otherDoctor, $nurse] as $viewer) {
        $this->actingAs($viewer)
            ->get(route('clinical.procedures.show', $procedureRecord))
            ->assertInertia(fn (Assert $page) => $page
                ->where('procedure.doctor.name', $responsibleDoctor->name)
                ->where('procedure.canManage', false)
                ->where('procedure.canComplete', false));

        $this->actingAs($viewer)
            ->put(route('clinical.procedures.update', $procedureRecord), procedureControllerDocumentation())
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('clinical.procedures.complete', $procedureRecord), ['expected_lock_version' => 1])
            ->assertForbidden();
    }

    $this->actingAs($otherDoctor)
        ->post(route('clinical.procedures.store', $decision->visit))
        ->assertSessionHasErrors('visit');
    $this->actingAs($nurse)
        ->post(route('clinical.procedures.store', $decision->visit))
        ->assertForbidden();
});

it('protects endpoints from guests and non-clinical roles', function (StaffRole $role) {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    [$decision] = procedureControllerReadyContext($doctor);
    $procedureRecord = app(StartProcedureRecord::class)->handle($doctor, $decision->visit);

    $this->get(route('clinical.procedures.index'))->assertRedirect(route('login'));
    $this->post(route('clinical.procedures.store', $decision->visit))->assertRedirect(route('login'));
    $this->get(route('clinical.procedures.show', $procedureRecord))->assertRedirect(route('login'));
    $this->put(route('clinical.procedures.update', $procedureRecord))->assertRedirect(route('login'));
    $this->post(route('clinical.procedures.complete', $procedureRecord))->assertRedirect(route('login'));

    $actor = User::factory()->forRole($role)->create();

    $this->actingAs($actor)->get(route('clinical.procedures.index'))->assertForbidden();
    $this->actingAs($actor)->post(route('clinical.procedures.store', $decision->visit))->assertForbidden();
    $this->actingAs($actor)->get(route('clinical.procedures.show', $procedureRecord))->assertForbidden();
    $this->actingAs($actor)->put(route('clinical.procedures.update', $procedureRecord))->assertForbidden();
    $this->actingAs($actor)->post(route('clinical.procedures.complete', $procedureRecord))->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('logs out an inactive Doctor before any procedure action', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    [$decision] = procedureControllerReadyContext($doctor);
    $doctor->is_active = false;
    $doctor->save();

    $this->actingAs($doctor)
        ->post(route('clinical.procedures.store', $decision->visit))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(ProcedureRecord::query()->count())->toBe(0);
});

it('exposes no deletion recovery discharge or consultation-finalization route', function () {
    expect(Route::has('clinical.procedures.index'))->toBeTrue()
        ->and(Route::has('clinical.procedures.store'))->toBeTrue()
        ->and(Route::has('clinical.procedures.show'))->toBeTrue()
        ->and(Route::has('clinical.procedures.update'))->toBeTrue()
        ->and(Route::has('clinical.procedures.complete'))->toBeTrue()
        ->and(Route::has('clinical.procedures.destroy'))->toBeFalse()
        ->and(Route::has('recovery.store'))->toBeFalse()
        ->and(Route::has('discharge.store'))->toBeFalse()
        ->and(Route::has('clinical.consultations.finalize'))->toBeFalse();
});

/** @return array{ProcedureDecision, PreProcedureReadiness} */
function procedureControllerReadyContext(User $doctor): array
{
    $decision = procedureControllerProcedureDecision($doctor);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);

    return [$decision, $readiness];
}

function procedureControllerProcedureDecision(User $doctor): ProcedureDecision
{
    return ProcedureDecision::factory()
        ->for(Consultation::factory()->for($doctor, 'doctor'))
        ->procedureRequired()
        ->createAuthoritativeDecisionFixture();
}

/**
 * @param  array<string, bool|int|string|null>  $overrides
 * @return array<string, bool|int|string|null>
 */
function procedureControllerDocumentation(array $overrides = []): array
{
    return [
        'expected_lock_version' => 1,
        'findings' => '  Documented findings.  ',
        'diagnosis_impression' => null,
        'specimens_taken' => false,
        'specimen_notes' => null,
        'complications' => null,
        'outcome' => '  Documented outcome.  ',
        'procedure_notes' => null,
        ...$overrides,
    ];
}
