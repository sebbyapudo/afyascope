<?php

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\User;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('lets the responsible Nurse assess readiness and projects the current decision safely', function () {
    [$recovery, $nurse] = readinessControllerRecovery();

    $this->actingAs($nurse)
        ->post(route('nursing.recovery.readiness.store', $recovery), [
            'criteria_met' => true,
            'clinical_concern_requires_escalation' => false,
            'assessment_note' => '  Standard recovery criteria satisfied.  ',
        ])
        ->assertRedirect(route('nursing.recovery.show', $recovery))
        ->assertSessionHas('status', 'Recovery is ready for discharge.');

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.status.value', 'ready_for_discharge')
            ->where('recovery.visit.nextStep', 'Ready for discharge')
            ->where('recovery.canManage', false)
            ->where('recovery.canAssessReadiness', false)
            ->where('recovery.canResolveEscalation', false)
            ->where('recovery.readinessAssessment.criteriaMet', true)
            ->where('recovery.readinessAssessment.clinicalConcernRequiresEscalation', false)
            ->where('recovery.readinessAssessment.assessmentNote', 'Standard recovery criteria satisfied.')
            ->where('recovery.readinessAssessment.assessedBy.name', $nurse->name)
            ->missing('recovery.readinessAssessment.assessedBy.id')
            ->missing('recovery.readinessAssessment.assessedBy.email')
            ->where('recovery.discharge', null)
            ->missing('recovery.audit'));
});

it('projects an open escalation to the Nurse owner and Doctor with capability-aware actions', function () {
    [$recovery, $nurse] = readinessControllerRecovery();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();

    $this->actingAs($nurse)->post(route('nursing.recovery.readiness.store', $recovery), [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => true,
        'assessment_note' => null,
        'escalation_reason' => 'Unexpected persistent drowsiness.',
    ])->assertRedirect(route('nursing.recovery.show', $recovery));

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.visit.nextStep', 'Doctor review required')
            ->where('recovery.canManage', true)
            ->where('recovery.canAssessReadiness', false)
            ->where('recovery.canResolveEscalation', false)
            ->has('recovery.escalations', 1)
            ->where('recovery.escalations.0.status.value', 'open')
            ->where('recovery.escalations.0.reason', 'Unexpected persistent drowsiness.')
            ->where('recovery.escalations.0.escalatedBy.name', $nurse->name)
            ->missing('recovery.escalations.0.escalatedBy.email'));

    $this->actingAs($doctor)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.canManage', false)
            ->where('recovery.canAssessReadiness', false)
            ->where('recovery.canResolveEscalation', true));

    $this->actingAs($otherNurse)
        ->get(route('nursing.recovery.show', $recovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recovery.canManage', false)
            ->where('recovery.canAssessReadiness', false)
            ->where('recovery.canResolveEscalation', false));
});

it('protects readiness writes from guests non-owners and all non-Nurse roles', function () {
    [$recovery, $owner] = readinessControllerRecovery();
    $payload = [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
    ];

    $this->post(route('nursing.recovery.readiness.store', $recovery), $payload)
        ->assertRedirect(route('login'));

    foreach ([StaffRole::Receptionist, StaffRole::Accountant, StaffRole::Doctor, StaffRole::Administrator, StaffRole::Management] as $role) {
        $this->actingAs(User::factory()->forRole($role)->create())
            ->post(route('nursing.recovery.readiness.store', $recovery), $payload)
            ->assertForbidden();
    }

    $this->actingAs(User::factory()->forRole(StaffRole::Nurse)->create())
        ->post(route('nursing.recovery.readiness.store', $recovery), $payload)
        ->assertForbidden();

    expect(RecoveryEscalation::query()->count())->toBe(0)
        ->and($recovery->fresh()->nurse_user_id)->toBe($owner->id);
});

it('keeps readiness distinct from discharge execution and completed lifecycle', function () {
    expect(Route::has('nursing.recovery.readiness.store'))->toBeTrue()
        ->and(Route::has('nursing.recovery.discharge.store'))->toBeTrue()
        ->and(Route::has('nursing.recovery.complete'))->toBeFalse()
        ->and(Route::has('discharges.store'))->toBeFalse();
});

it('keeps active recovery queues and Patient workflow projections aligned with readiness state', function () {
    [$readyRecovery, $nurse] = readinessControllerRecovery();
    $this->actingAs($nurse)->post(route('nursing.recovery.readiness.store', $readyRecovery), [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
    ])->assertRedirect();

    [$escalatedRecovery, $escalatedNurse] = readinessControllerRecovery();
    $this->actingAs($escalatedNurse)->post(route('nursing.recovery.readiness.store', $escalatedRecovery), [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => true,
        'escalation_reason' => 'Doctor review required.',
    ])->assertRedirect();

    $this->actingAs($nurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeRecoveries.data', fn ($items): bool => collect($items)
                ->where('id', $readyRecovery->id)
                ->where('visit.nextStep', 'Ready for discharge')
                ->isNotEmpty()));

    $this->actingAs($escalatedNurse)
        ->get(route('nursing.recovery.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeRecoveries.data', fn ($items): bool => collect($items)
                ->where('id', $escalatedRecovery->id)
                ->where('visit.nextStep', 'Doctor review required')
                ->isNotEmpty()));

    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $this->actingAs($receptionist)
        ->get(route('patients.show', $readyRecovery->visit->patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.nextStep', 'Ready for discharge'));
});

/** @return array{0: RecoveryEpisode, 1: User} */
function readinessControllerRecovery(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()->ready()->createAuthoritativePreparationFixture(
        $decision,
        User::factory()->forRole(StaffRole::Nurse)->create(),
    );
    $procedure = ProcedureRecord::factory()->completed()->createAuthoritativeProcedureFixture($decision, $preparation);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    return [RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedure, $nurse), $nurse];
}
