<?php

use App\Actions\Nursing\StartRecoveryEpisode;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryObservation;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('lets the responsible Nurse record an observation and projects sanitized serial history', function () {
    [$recovery, $nurse] = observationControllerRecovery();

    $this->actingAs($nurse)
        ->post(route('nursing.recovery.observations.store', $recovery), observationHttpAttributes([
            'nursing_note' => '  Reassured patient.  ',
        ]))
        ->assertRedirect(route('nursing.recovery.show', $recovery))
        ->assertSessionHas('status', 'Recovery observation was recorded.');

    $observation = RecoveryObservation::query()->sole();
    expect($observation->nursing_note)->toBe('Reassured patient.')
        ->and($observation->recorded_by_user_id)->toBe($nurse->id);

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.canManage', true)
            ->has('recovery.observations', 1)
            ->where('recovery.observations.0.generalRecoveryStatus', 'Awake and recovering comfortably')
            ->where('recovery.observations.0.recordedBy.name', $nurse->name)
            ->missing('recovery.observations.0.recordedBy.email')
            ->missing('recovery.observations.0.recordedBy.id')
            ->missing('recovery.observations.0.recoveryEpisodeId')
            ->missing('recovery.observations.0.visitId')
            ->missing('recovery.bill')
            ->missing('recovery.financialClearance'));
});

it('keeps other Nurses and Doctors read-only and protects writes from guests and other roles', function () {
    [$recovery, $owner] = observationControllerRecovery();
    $payload = observationHttpAttributes();

    $this->post(route('nursing.recovery.observations.store', $recovery), $payload)
        ->assertRedirect(route('login'));

    foreach ([StaffRole::Nurse, StaffRole::Doctor, StaffRole::Receptionist, StaffRole::Accountant, StaffRole::Administrator, StaffRole::Management] as $role) {
        $actor = User::factory()->forRole($role)->create();
        $response = $this->actingAs($actor)->get(route('nursing.recovery.show', $recovery));

        if (in_array($role, [StaffRole::Nurse, StaffRole::Doctor], true)) {
            $response->assertInertia(fn (Assert $page) => $page->where('recovery.canManage', false));
        } else {
            $response->assertForbidden();
        }

        $this->actingAs($actor)
            ->post(route('nursing.recovery.observations.store', $recovery), $payload)
            ->assertForbidden();
    }

    expect(RecoveryObservation::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryObservationRecorded)->count())->toBe(0)
        ->and($recovery->fresh()->nurse_user_id)->toBe($owner->id);
});

it('validates clinical structure and rejects forged server-owned context', function () {
    [$recovery, $nurse] = observationControllerRecovery();

    $this->actingAs($nurse)
        ->post(route('nursing.recovery.observations.store', $recovery), observationHttpAttributes([
            'general_recovery_status' => ' ',
            'systolic_blood_pressure' => 120,
            'diastolic_blood_pressure' => null,
            'recorded_by_user_id' => 999,
            'recorded_at' => '2020-01-01 00:00:00',
            'status' => 'completed',
        ]))
        ->assertSessionHasErrors([
            'general_recovery_status', 'diastolic_blood_pressure',
            'recorded_by_user_id', 'recorded_at', 'status',
        ]);

    expect(RecoveryObservation::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::RecoveryObservationRecorded)->count())->toBe(0);
});

it('exposes only the append-only observation store route and no completion lifecycle', function () {
    expect(Route::has('nursing.recovery.observations.store'))->toBeTrue()
        ->and(Route::has('nursing.recovery.observations.update'))->toBeFalse()
        ->and(Route::has('nursing.recovery.observations.destroy'))->toBeFalse()
        ->and(Route::has('nursing.recovery.complete'))->toBeFalse()
        ->and(Route::has('nursing.recovery.discharge'))->toBeFalse();
});

/** @return array{0: RecoveryEpisode, 1: User} */
function observationControllerRecovery(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $readiness);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    return [app(StartRecoveryEpisode::class)->handle($nurse, $procedure), $nurse];
}

/** @param array<string, mixed> $overrides */
function observationHttpAttributes(array $overrides = []): array
{
    return array_replace([
        'general_recovery_status' => 'Awake and recovering comfortably',
        'pain_score' => 3,
        'nausea' => false,
        'vomiting' => false,
        'systolic_blood_pressure' => 120,
        'diastolic_blood_pressure' => 80,
        'pulse_rate' => 72,
        'respiratory_rate' => 16,
        'oxygen_saturation' => 98,
        'supplemental_oxygen' => false,
        'nursing_note' => null,
    ], $overrides);
}
