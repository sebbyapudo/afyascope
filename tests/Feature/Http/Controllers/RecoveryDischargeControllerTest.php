<?php

use App\Actions\Nursing\AssessRecoveryReadiness;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('lets the responsible Nurse explicitly discharge and renders a sanitized finalized summary', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeController();
    $payload = dischargeControllerPayload();

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.status.value', 'ready_for_discharge')
            ->where('recovery.canDischarge', true)
            ->where('recovery.discharge', null));

    $response = $this->actingAs($nurse)
        ->post(route('nursing.recovery.discharge.store', $recovery), $payload);
    $discharge = RecoveryDischarge::query()->sole();

    $response
        ->assertRedirect(route('nursing.recovery.show', $recovery))
        ->assertSessionHas('status', "Discharge {$discharge->discharge_number} was finalized.");

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.status.value', 'completed')
            ->where('recovery.status.label', 'Discharged')
            ->where('recovery.visit.nextStep', 'Discharged / Completed')
            ->where('recovery.canManage', false)
            ->where('recovery.canAssessReadiness', false)
            ->where('recovery.canDischarge', false)
            ->where('recovery.discharge.dischargeNumber', $discharge->discharge_number)
            ->where('recovery.discharge.conditionSummary', $payload['condition_summary'])
            ->where('recovery.discharge.accompanimentStatus.value', 'accompanied')
            ->where('recovery.discharge.disposition.value', 'home')
            ->where('recovery.discharge.generalCareInstructions', $payload['general_care_instructions'])
            ->where('recovery.discharge.dischargedBy.name', $nurse->name)
            ->missing('recovery.discharge.dischargedBy.id')
            ->missing('recovery.discharge.dischargedBy.email')
            ->missing('recovery.discharge.dischargedBy.role')
            ->missing('recovery.discharge.dischargeByUserId')
            ->missing('recovery.discharge.audit'));

    $this->actingAs($recovery->procedureRecord->doctor)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.canDischarge', false)
            ->where('recovery.discharge.dischargeNumber', $discharge->discharge_number)
            ->where('recovery.discharge.dischargedBy.name', $nurse->name));
});

it('allows authorized clinical viewers to read but never execute another Nurses discharge', function () {
    [$recovery, $owner] = readyRecoveryForDischargeController();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();

    foreach ([$doctor, $otherNurse] as $viewer) {
        $this->actingAs($viewer)
            ->get(route('nursing.recovery.show', $recovery))
            ->assertInertia(fn (Assert $page) => $page
                ->where('recovery.canDischarge', false)
                ->where('recovery.nurse.name', $owner->name));

        $this->actingAs($viewer)
            ->post(route('nursing.recovery.discharge.store', $recovery), dischargeControllerPayload())
            ->assertForbidden();
    }

    expect(RecoveryDischarge::query()->count())->toBe(0);
});

it('protects discharge execution from guests inactive staff and all unauthorized roles', function () {
    [$recovery, $owner] = readyRecoveryForDischargeController();

    $this->post(route('nursing.recovery.discharge.store', $recovery), dischargeControllerPayload())
        ->assertRedirect(route('login'));

    foreach ([StaffRole::Receptionist, StaffRole::Accountant, StaffRole::Doctor, StaffRole::Administrator, StaffRole::Management] as $role) {
        $this->actingAs(User::factory()->forRole($role)->create())
            ->post(route('nursing.recovery.discharge.store', $recovery), dischargeControllerPayload())
            ->assertForbidden();
    }

    User::query()->whereKey($owner->getKey())->update(['is_active' => false]);
    $this->actingAs($owner->refresh())
        ->post(route('nursing.recovery.discharge.store', $recovery), dischargeControllerPayload())
        ->assertRedirect(route('login'));

    expect(RecoveryDischarge::query()->count())->toBe(0);
});

it('requires complete documentation explicit confirmation and rejects forged finalization data', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeController();

    $this->actingAs($nurse)
        ->post(route('nursing.recovery.discharge.store', $recovery), [])
        ->assertSessionHasErrors([
            'condition_summary',
            'accompaniment_status',
            'disposition',
            'general_care_instructions',
            'activity_driving_instructions',
            'diet_fluids_instructions',
            'warning_signs_instructions',
            'confirm_discharge',
        ]);

    $forged = dischargeControllerPayload([
        'recovery_episode_id' => 999,
        'discharged_by_user_id' => 999,
        'discharge_number' => 'DSC-FORGED',
        'discharged_at' => '2020-01-01 00:00:00',
        'status' => 'completed',
        'completed_at' => '2020-01-01 00:00:00',
    ]);

    $this->actingAs($nurse)
        ->post(route('nursing.recovery.discharge.store', $recovery), $forged)
        ->assertSessionHasErrors([
            'recovery_episode_id',
            'discharged_by_user_id',
            'discharge_number',
            'discharged_at',
            'status',
            'completed_at',
        ]);

    expect(RecoveryDischarge::query()->count())->toBe(0)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::ReadyForDischarge);
});

it('keeps ready work actionable then removes discharged recovery from the active queue without completing the Visit', function () {
    [$recovery, $nurse] = readyRecoveryForDischargeController();

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeRecoveries.data', fn ($items): bool => collect($items)
                ->where('id', $recovery->id)
                ->where('visit.nextStep', 'Ready for discharge')
                ->isNotEmpty()));

    $this->actingAs($nurse)
        ->post(route('nursing.recovery.discharge.store', $recovery), dischargeControllerPayload())
        ->assertRedirect();

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeRecoveries.data', fn ($items): bool => collect($items)
                ->where('id', $recovery->id)->isEmpty()));

    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $this->actingAs($receptionist)
        ->get(route('patients.show', $recovery->visit->patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.status.value', VisitStatus::Completed->value)
            ->where('visitHistory.data.0.nextStep', 'Discharged / Completed'));

    $this->actingAs($recovery->procedureRecord->doctor)
        ->get(route('clinical.procedures.show', $recovery->procedureRecord))
        ->assertInertia(fn (Assert $page) => $page
            ->where('procedure.visit.nextStep', 'Discharged / Completed'));

    expect($recovery->visit->fresh()->status)->toBe(VisitStatus::Completed)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryDischarged)->count())->toBe(1)
        ->and(Route::has('visits.complete'))->toBeFalse()
        ->and(Route::has('patient-timeline.index'))->toBeFalse();
});

it('exposes only the final discharge action and no edit delete or Visit-completion route', function () {
    expect(Route::has('nursing.recovery.discharge.store'))->toBeTrue()
        ->and(Route::has('nursing.recovery.discharge.update'))->toBeFalse()
        ->and(Route::has('nursing.recovery.discharge.destroy'))->toBeFalse()
        ->and(Route::has('nursing.recovery.complete'))->toBeFalse()
        ->and(Route::has('visits.complete'))->toBeFalse();
});

/** @return array{0: RecoveryEpisode, 1: User} */
function readyRecoveryForDischargeController(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $preparation);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $recovery = RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedure, $nurse);
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
    ]);

    return [$recovery->refresh(), $nurse];
}

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function dischargeControllerPayload(array $overrides = []): array
{
    return array_replace([
        'condition_summary' => 'Alert and comfortable at discharge.',
        'accompaniment_status' => 'accompanied',
        'disposition' => 'home',
        'nursing_note' => 'Patient and escort confirmed understanding.',
        'general_care_instructions' => 'Rest for the remainder of today.',
        'activity_driving_instructions' => 'Do not drive for the advised period.',
        'diet_fluids_instructions' => 'Take fluids and resume diet as advised.',
        'medication_instructions' => 'Continue usual medicines as instructed.',
        'warning_signs_instructions' => 'Seek urgent care for severe pain or bleeding.',
        'follow_up_instructions' => 'Attend the planned clinic review.',
        'confirm_discharge' => true,
    ], $overrides);
}
