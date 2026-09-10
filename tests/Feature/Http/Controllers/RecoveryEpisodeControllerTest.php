<?php

use App\Actions\Nursing\StartRecoveryEpisode;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('shows completed procedures without recovery oldest first in the Nurse queue', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    $this->travelTo('2026-09-09 08:00:00');
    $oldest = recoveryControllerCompletedProcedure();
    $this->travelTo('2026-09-09 09:00:00');
    $newer = recoveryControllerCompletedProcedure();
    $this->travelTo('2026-09-09 10:00:00');
    $alreadyStarted = recoveryControllerCompletedProcedure();
    app(StartRecoveryEpisode::class)->handle($nurse, $alreadyStarted);
    recoveryControllerCompletedProcedure(completed: false);
    $this->travelBack();

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('nursing/recovery/index')
            ->where('awaitingRecoveries.data', fn ($items): bool => collect($items)
                ->pluck('id')->all() === [$oldest->id, $newer->id])
            ->where('awaitingRecoveries.data.0.visit.nextStep', 'Ready for Nursing recovery')
            ->where('awaitingRecoveries.data.0.patient.patientNumber', $oldest->visit->patient->patient_number)
            ->where('awaitingRecoveries.data.0.procedure.name', $oldest->serviceCatalogItem->name)
            ->where('awaitingRecoveries.data.0.doctor.name', $oldest->doctor->name)
            ->where('awaitingRecoveries.pagination.total', 2)
            ->where('awaitingRecoveries.pagination.pageName', 'awaiting_page')
            ->where('activeRecoveries.data.0.id', $alreadyStarted->recoveryEpisode->id)
            ->where('activeRecoveries.pagination.pageName', 'active_page')
            ->missing('awaitingRecoveries.data.0.findings')
            ->missing('awaitingRecoveries.data.0.bill')
            ->missing('awaitingRecoveries.data.0.payment'));
});

it('paginates the recovery queue at fifteen completed procedures', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    foreach (range(1, 16) as $minute) {
        $this->travelTo(sprintf('2026-09-09 11:%02d:00', $minute));
        recoveryControllerCompletedProcedure();
    }

    $this->travelBack();

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('awaitingRecoveries.data', 15)
            ->where('awaitingRecoveries.pagination.currentPage', 1)
            ->where('awaitingRecoveries.pagination.perPage', 15)
            ->where('awaitingRecoveries.pagination.lastPage', 2)
            ->where('awaitingRecoveries.pagination.total', 16));

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index', ['awaiting_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('awaitingRecoveries.data', 1)
            ->where('awaitingRecoveries.pagination.currentPage', 2));
});

it('shows only the responsible Nurse active recoveries oldest first with independent pagination', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $ownedRecoveries = collect();

    foreach (range(1, 16) as $minute) {
        $this->travelTo(sprintf('2026-09-09 12:%02d:00', $minute));
        $ownedRecoveries->push(app(StartRecoveryEpisode::class)->handle(
            $nurse,
            recoveryControllerCompletedProcedure(),
        ));
    }

    app(StartRecoveryEpisode::class)->handle($otherNurse, recoveryControllerCompletedProcedure());
    $this->travelBack();

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activeRecoveries.data', 15)
            ->where('activeRecoveries.data.0.id', $ownedRecoveries->first()->id)
            ->where('activeRecoveries.pagination.currentPage', 1)
            ->where('activeRecoveries.pagination.pageName', 'active_page')
            ->where('activeRecoveries.pagination.total', 16)
            ->where('awaitingRecoveries.pagination.pageName', 'awaiting_page'));

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index', ['active_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activeRecoveries.data', 1)
            ->where('activeRecoveries.data.0.id', $ownedRecoveries->last()->id)
            ->where('activeRecoveries.pagination.currentPage', 2));
});

it('shows a confirmation context and explicitly starts recovery', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $procedureRecord = recoveryControllerCompletedProcedure();

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.create', $procedureRecord))
        ->assertInertia(fn (Assert $page) => $page
            ->component('nursing/recovery/create')
            ->where('procedure.id', $procedureRecord->id)
            ->where('procedure.visit.nextStep', 'Ready for Nursing recovery')
            ->where('procedure.patient.patientNumber', $procedureRecord->visit->patient->patient_number)
            ->where('procedure.doctor.name', $procedureRecord->doctor->name)
            ->missing('procedure.findings')
            ->missing('procedure.bill'));

    $response = $this->actingAs($nurse)
        ->post(route('nursing.recovery.store', $procedureRecord));
    $recovery = RecoveryEpisode::query()->sole();

    $response
        ->assertRedirect(route('nursing.recovery.show', $recovery))
        ->assertSessionHas('status', "Recovery {$recovery->recovery_number} was started.");

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->component('nursing/recovery/show')
            ->where('recovery.recoveryNumber', $recovery->recovery_number)
            ->where('recovery.status.value', 'in_progress')
            ->where('recovery.canManage', true)
            ->where('recovery.nurse.name', $nurse->name)
            ->where('recovery.doctor.name', $procedureRecord->doctor->name)
            ->where('recovery.patient.patientNumber', $procedureRecord->visit->patient->patient_number)
            ->where('recovery.visit.nextStep', 'Recovery in progress')
            ->has('recovery.observations', 0)
            ->missing('recovery.findings')
            ->missing('recovery.bill')
            ->missing('recovery.payment')
            ->missing('recovery.receipt')
            ->missing('recovery.financialClearance')
            ->missing('recovery.doctor.email')
            ->missing('recovery.nurse.email'));

    expect(AuditLog::query()->where('action', AuditAction::RecoveryStarted)->count())->toBe(1);
});

it('allows other Nurses and Doctors to view recovery without takeover controls', function () {
    $owner = User::factory()->forRole(StaffRole::Nurse)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $recovery = app(StartRecoveryEpisode::class)->handle($owner, recoveryControllerCompletedProcedure());

    foreach ([$otherNurse, $doctor] as $viewer) {
        $this->actingAs($viewer)
            ->get(route('nursing.recovery.show', $recovery))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recovery.nurse.name', $owner->name)
                ->where('recovery.canManage', false));
    }

    $this->actingAs($otherNurse)
        ->post(route('nursing.recovery.store', $recovery->procedureRecord))
        ->assertSessionHasErrors('procedure');

    expect($recovery->fresh()->nurse_user_id)->toBe($owner->id)
        ->and(RecoveryEpisode::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryStarted)->count())->toBe(1);
});

it('rejects client-forged recovery ownership lifecycle timestamps and later-stage fields', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $procedureRecord = recoveryControllerCompletedProcedure();
    $forged = [
        'visit_id' => 999_999,
        'procedure_record_id' => 999_999,
        'nurse_user_id' => 999_999,
        'recovery_number' => 'REC-FORGED',
        'status' => 'completed',
        'started_at' => '2020-01-01 00:00:00',
        'completed_at' => '2020-01-01 01:00:00',
        'observations' => 'Forged observation.',
        'recovery_notes' => 'Forged recovery note.',
        'discharge_status' => 'discharged',
    ];

    $this->actingAs($nurse)
        ->post(route('nursing.recovery.store', $procedureRecord), $forged)
        ->assertSessionHasErrors(array_keys($forged));

    expect(RecoveryEpisode::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryStarted)->count())->toBe(0);
});

it('protects recovery routes from guests and non-Nursing operational roles', function (StaffRole $role) {
    $procedureRecord = recoveryControllerCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recovery = app(StartRecoveryEpisode::class)->handle($nurse, $procedureRecord);

    $this->get(route('nursing.recovery.index'))->assertRedirect(route('login'));
    $this->get(route('nursing.recovery.create', $procedureRecord))->assertRedirect(route('login'));
    $this->post(route('nursing.recovery.store', $procedureRecord))->assertRedirect(route('login'));
    $this->get(route('nursing.recovery.show', $recovery))->assertRedirect(route('login'));

    $actor = User::factory()->forRole($role)->create();

    $this->actingAs($actor)->get(route('nursing.recovery.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('nursing.recovery.create', $procedureRecord))->assertForbidden();
    $this->actingAs($actor)->post(route('nursing.recovery.store', $procedureRecord))->assertForbidden();
    $this->actingAs($actor)->get(route('nursing.recovery.show', $recovery))->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('denies Doctors the Nurse queue and start while retaining linked read-only recovery access', function () {
    $procedureRecord = recoveryControllerCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recovery = app(StartRecoveryEpisode::class)->handle($nurse, $procedureRecord);
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    $this->actingAs($doctor)->get(route('nursing.recovery.index'))->assertForbidden();
    $this->actingAs($doctor)->get(route('nursing.recovery.create', $procedureRecord))->assertForbidden();
    $this->actingAs($doctor)->post(route('nursing.recovery.store', $procedureRecord))->assertForbidden();
    $this->actingAs($doctor)->get(route('nursing.recovery.show', $recovery))->assertOk();
});

it('projects recovery consistently on Visit and the responsible Doctor procedure workspace', function () {
    $procedureRecord = recoveryControllerCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recovery = app(StartRecoveryEpisode::class)->handle($nurse, $procedureRecord);

    expect($procedureRecord->visit->fresh()->workflowMessage())->toBe('Recovery in progress');

    $this->actingAs($procedureRecord->doctor)
        ->get(route('clinical.procedures.show', $procedureRecord))
        ->assertInertia(fn (Assert $page) => $page
            ->where('procedure.visit.nextStep', 'Recovery in progress')
            ->where('procedure.recovery.id', $recovery->id)
            ->where('procedure.recovery.recoveryNumber', $recovery->recovery_number)
            ->where('procedure.recovery.nurse.name', $nurse->name)
            ->missing('procedure.recovery.observations'));

    $consultation = $procedureRecord->procedureDecision->consultation;
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();

    $this->actingAs($receptionist)
        ->get(route('visits.show', $procedureRecord->visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.nextStep', 'Recovery in progress'));

    $this->actingAs($receptionist)
        ->get(route('patients.show', $procedureRecord->visit->patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.nextStep', 'Recovery in progress'));

    $this->actingAs($procedureRecord->doctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.visit.nextStep', 'Recovery in progress'));
});

it('logs out an inactive Nurse before any recovery route can change state', function () {
    $procedureRecord = recoveryControllerCompletedProcedure();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->inactive()->create();

    $this->actingAs($nurse)
        ->post(route('nursing.recovery.store', $procedureRecord))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(RecoveryEpisode::query()->count())->toBe(0);
});

it('exposes only the authorized discharge action with no generic completion update or deletion route', function () {
    expect(Route::has('nursing.recovery.index'))->toBeTrue()
        ->and(Route::has('nursing.recovery.create'))->toBeTrue()
        ->and(Route::has('nursing.recovery.store'))->toBeTrue()
        ->and(Route::has('nursing.recovery.show'))->toBeTrue()
        ->and(Route::has('nursing.recovery.update'))->toBeFalse()
        ->and(Route::has('nursing.recovery.complete'))->toBeFalse()
        ->and(Route::has('nursing.recovery.destroy'))->toBeFalse()
        ->and(Route::has('nursing.recovery.discharge.store'))->toBeTrue()
        ->and(Route::has('nursing.recovery.discharge.update'))->toBeFalse()
        ->and(Route::has('nursing.recovery.discharge.destroy'))->toBeFalse();
});

function recoveryControllerCompletedProcedure(bool $completed = true): ProcedureRecord
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparationNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $preparationNurse);
    $factory = ProcedureRecord::factory();

    if ($completed) {
        $factory = $factory->completed();
    }

    return $factory->createAuthoritativeProcedureFixture($decision, $readiness);
}
