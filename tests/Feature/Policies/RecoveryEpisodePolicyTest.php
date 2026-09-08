<?php

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

it('grants recovery management only to Nurses and read visibility to Nurses and Doctors', function (
    StaffRole $role,
    bool $canView,
    bool $canManage,
) {
    $actor = User::factory()->forRole($role)->create();

    expect(Gate::forUser($actor)->allows(StaffPermission::RecoveryView))->toBe($canView)
        ->and(Gate::forUser($actor)->allows(StaffPermission::RecoveryManage))->toBe($canManage)
        ->and(Gate::forUser($actor)->allows('viewAny', RecoveryEpisode::class))->toBe($canView)
        ->and(Gate::forUser($actor)->allows('create', RecoveryEpisode::class))->toBe($canManage);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false, false],
    'Accountant' => [StaffRole::Accountant, false, false],
    'Doctor' => [StaffRole::Doctor, true, false],
    'Nurse' => [StaffRole::Nurse, true, true],
    'Administrator' => [StaffRole::Administrator, false, false],
    'Management' => [StaffRole::Management, false, false],
]);

it('allows only the responsible Nurse to manage an in-progress recovery episode', function () {
    $responsibleNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $recoveryEpisode = recoveryEpisodePolicyFixture($responsibleNurse);

    expect(Gate::forUser($responsibleNurse)->allows('view', $recoveryEpisode))->toBeTrue()
        ->and(Gate::forUser($otherNurse)->allows('view', $recoveryEpisode))->toBeTrue()
        ->and(Gate::forUser($doctor)->allows('view', $recoveryEpisode))->toBeTrue()
        ->and(Gate::forUser($responsibleNurse)->allows('update', $recoveryEpisode))->toBeTrue()
        ->and(Gate::forUser($otherNurse)->denies('update', $recoveryEpisode))->toBeTrue()
        ->and(Gate::forUser($doctor)->denies('update', $recoveryEpisode))->toBeTrue();
});

it('denies completed-record management and inactive clinical users', function () {
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $inactiveNurse = User::factory()->forRole(StaffRole::Nurse)->inactive()->create();
    $inactiveDoctor = User::factory()->forRole(StaffRole::Doctor)->inactive()->create();
    $recoveryEpisode = recoveryEpisodePolicyFixture($nurse, completed: true);

    expect(Gate::forUser($nurse)->denies('update', $recoveryEpisode))->toBeTrue()
        ->and(Gate::forUser($inactiveNurse)->denies(StaffPermission::RecoveryView))->toBeTrue()
        ->and(Gate::forUser($inactiveNurse)->denies(StaffPermission::RecoveryManage))->toBeTrue()
        ->and(Gate::forUser($inactiveDoctor)->denies(StaffPermission::RecoveryView))->toBeTrue();
});

function recoveryEpisodePolicyFixture(User $nurse, bool $completed = false): RecoveryEpisode
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $preparationNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $preparationNurse);
    $procedureRecord = ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($decision, $readiness);
    $factory = RecoveryEpisode::factory();

    if ($completed) {
        $factory = $factory->completed();
    }

    return $factory->createAuthoritativeRecoveryFixture($procedureRecord, $nurse);
}
