<?php

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

it('grants the Nursing capability only to the Nurse role', function (StaffRole $role, bool $allowed) {
    $actor = User::factory()->forRole($role)->create();

    expect(Gate::forUser($actor)->allows(StaffPermission::NursingManage))->toBe($allowed)
        ->and(Gate::forUser($actor)->allows('create', PreProcedureReadiness::class))->toBe($allowed);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false],
    'Accountant' => [StaffRole::Accountant, false],
    'Doctor' => [StaffRole::Doctor, false],
    'Nurse' => [StaffRole::Nurse, true],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

it('allows only the responsible Nurse to change an in-progress preparation', function () {
    $responsibleNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()->createAuthoritativePreparationFixture(
        $decision,
        $responsibleNurse,
    );

    expect(Gate::forUser($responsibleNurse)->allows('view', $readiness))->toBeTrue()
        ->and(Gate::forUser($otherNurse)->allows('view', $readiness))->toBeTrue()
        ->and(Gate::forUser($responsibleNurse)->allows('update', $readiness))->toBeTrue()
        ->and(Gate::forUser($responsibleNurse)->allows('complete', $readiness))->toBeTrue()
        ->and(Gate::forUser($otherNurse)->denies('update', $readiness))->toBeTrue()
        ->and(Gate::forUser($otherNurse)->denies('complete', $readiness))->toBeTrue();
});

it('denies changes after readiness completion and denies inactive Nurses', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $inactiveNurse = User::factory()->forRole(StaffRole::Nurse)->inactive()->create();
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);

    expect(Gate::forUser($nurse)->denies('update', $readiness))->toBeTrue()
        ->and(Gate::forUser($nurse)->denies('complete', $readiness))->toBeTrue()
        ->and(Gate::forUser($inactiveNurse)->denies(StaffPermission::NursingManage))->toBeTrue();
});
