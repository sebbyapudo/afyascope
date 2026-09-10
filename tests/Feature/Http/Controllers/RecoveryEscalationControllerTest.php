<?php

use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\User;
use App\RecoveryEscalationResolution;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

it('gives Doctors a focused oldest-first queue containing only open recovery escalations', function () {
    [$olderRecovery, $olderNurse] = escalationControllerRecovery();
    $this->travelTo('2026-09-10 09:00:00');
    $older = escalationThroughAssessment($olderRecovery, $olderNurse, 'Older concern.');
    [$newerRecovery, $newerNurse] = escalationControllerRecovery();
    $this->travelTo('2026-09-10 09:10:00');
    $newer = escalationThroughAssessment($newerRecovery, $newerNurse, 'Newer concern.');
    [$resolvedRecovery, $resolvedNurse] = escalationControllerRecovery();
    $resolved = escalationThroughAssessment($resolvedRecovery, $resolvedNurse, 'Resolved concern.');
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $resolved->resolveFromClinicalWorkflow($doctor, RecoveryEscalationResolution::ContinueMonitoring, null);
    $this->travelBack();

    $this->actingAs($doctor)
        ->get(route('clinical.recovery-escalations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinical/recovery-escalations/index')
            ->where('auth.capabilities.reviewRecoveryEscalations', true)
            ->has('escalations', 2)
            ->where('escalations.0.id', $older->id)
            ->where('escalations.1.id', $newer->id)
            ->where('escalations.0.reason', 'Older concern.')
            ->where('escalations.0.recovery.nurse.name', $olderNurse->name)
            ->missing('escalations.0.recovery.nurse.email')
            ->missing('escalations.0.patient.dateOfBirth')
            ->missing('escalations.0.audit'));
});

it('lets a Doctor resolve from the workspace and removes the case from the open queue', function () {
    [$recovery, $nurse] = escalationControllerRecovery();
    $escalation = escalationThroughAssessment($recovery, $nurse, 'Review required.');
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();

    $this->actingAs($doctor)
        ->put(route('clinical.recovery-escalations.update', $escalation), [
            'resolution' => 'clinically_cleared',
            'resolution_note' => '  Clinically stable for Nurse reassessment.  ',
        ])
        ->assertRedirect(route('nursing.recovery.show', $recovery))
        ->assertSessionHas('status', 'Recovery escalation was resolved and returned to Nursing.');

    $this->actingAs($doctor)
        ->get(route('clinical.recovery-escalations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('escalations', 0));

    expect($escalation->fresh()->resolution_note)->toBe('Clinically stable for Nurse reassessment.');
});

it('denies the Doctor queue and resolution endpoint to guests and all other roles', function () {
    [$recovery, $nurse] = escalationControllerRecovery();
    $escalation = escalationThroughAssessment($recovery, $nurse, 'Review required.');
    $payload = ['resolution' => 'continue_monitoring'];

    $this->get(route('clinical.recovery-escalations.index'))->assertRedirect(route('login'));
    $this->put(route('clinical.recovery-escalations.update', $escalation), $payload)
        ->assertRedirect(route('login'));

    foreach ([StaffRole::Receptionist, StaffRole::Accountant, StaffRole::Nurse, StaffRole::Administrator, StaffRole::Management] as $role) {
        $actor = User::factory()->forRole($role)->create();
        $this->actingAs($actor)->get(route('clinical.recovery-escalations.index'))->assertForbidden();
        $this->actingAs($actor)
            ->put(route('clinical.recovery-escalations.update', $escalation), $payload)
            ->assertForbidden();
    }

    expect($escalation->fresh()->status->value)->toBe('open');
});

/** @return array{0: RecoveryEpisode, 1: User} */
function escalationControllerRecovery(): array
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

function escalationThroughAssessment(
    RecoveryEpisode $recovery,
    User $nurse,
    string $reason,
): RecoveryEscalation {
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => true,
        'escalation_reason' => $reason,
    ]);

    return $recovery->escalations()->latest('id')->firstOrFail();
}
