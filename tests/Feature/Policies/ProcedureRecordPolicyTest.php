<?php

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Support\Facades\Gate;

it('grants procedure management only to Doctors and read visibility to Doctors and Nurses', function (
    StaffRole $role,
    bool $canView,
    bool $canManage,
) {
    $actor = User::factory()->forRole($role)->create();

    expect(Gate::forUser($actor)->allows(StaffPermission::ProceduresView))->toBe($canView)
        ->and(Gate::forUser($actor)->allows(StaffPermission::ProceduresManage))->toBe($canManage)
        ->and(Gate::forUser($actor)->allows('viewAny', ProcedureRecord::class))->toBe($canManage)
        ->and(Gate::forUser($actor)->allows('create', ProcedureRecord::class))->toBe($canManage);
})->with([
    'Receptionist' => [StaffRole::Receptionist, false, false],
    'Accountant' => [StaffRole::Accountant, false, false],
    'Doctor' => [StaffRole::Doctor, true, true],
    'Nurse' => [StaffRole::Nurse, true, false],
    'Administrator' => [StaffRole::Administrator, false, false],
    'Management' => [StaffRole::Management, false, false],
]);

it('allows only the responsible Doctor to modify an in-progress procedure', function () {
    $procedureRecord = procedureRecordPolicyFixture();
    $responsibleDoctor = $procedureRecord->doctor;
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();

    expect(Gate::forUser($responsibleDoctor)->allows('view', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($otherDoctor)->allows('view', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($nurse)->allows('view', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($responsibleDoctor)->allows('update', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($responsibleDoctor)->allows('complete', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($otherDoctor)->denies('update', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($otherDoctor)->denies('complete', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($nurse)->denies('update', $procedureRecord))->toBeTrue();
});

it('denies completed-record changes and inactive Doctor access', function () {
    $procedureRecord = procedureRecordPolicyFixture(completed: true);
    $inactiveDoctor = User::factory()->forRole(StaffRole::Doctor)->inactive()->create();

    expect(Gate::forUser($procedureRecord->doctor)->denies('update', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($procedureRecord->doctor)->denies('complete', $procedureRecord))->toBeTrue()
        ->and(Gate::forUser($inactiveDoctor)->denies(StaffPermission::ProceduresView))->toBeTrue()
        ->and(Gate::forUser($inactiveDoctor)->denies(StaffPermission::ProceduresManage))->toBeTrue();
});

function procedureRecordPolicyFixture(bool $completed = false): ProcedureRecord
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);
    $factory = ProcedureRecord::factory();

    if ($completed) {
        $factory = $factory->completed();
    }

    return $factory->createAuthoritativeProcedureFixture($decision, $readiness);
}
