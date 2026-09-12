<?php

use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Actions\Staff\CreateStaffUser;
use App\Actions\Staff\UpdateStaffUser;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Consultation;
use App\Models\Payment;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\Receipt;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('creates staff accounts with each canonical fixed role', function (StaffRole $role) {
    Notification::fake();
    $administrator = staffCompletionAdministrator();

    $this->actingAs($administrator)
        ->post(route('staff.store'), [
            'name' => "{$role->displayName()} Staff",
            'email' => "{$role->value}@example.com",
            'role' => $role->value,
            'is_active' => true,
        ])
        ->assertRedirect(route('staff.index'));

    $staffUser = User::query()->where('email', "{$role->value}@example.com")->sole();

    expect($staffUser->role->slug)->toBe($role->value)
        ->and($staffUser->is_active)->toBeTrue();
    Notification::assertSentTo($staffUser, ResetPassword::class);
})->with(StaffRole::cases());

it('enforces active Administrator authorization at staff action boundaries', function () {
    $inactiveAdministrator = User::factory()
        ->forRole(StaffRole::Administrator)
        ->inactive()
        ->create();
    $staffUser = User::factory()->create();

    expect(fn () => app(CreateStaffUser::class)->handle($inactiveAdministrator, [
        'name' => 'Unauthorized Staff',
        'email' => 'unauthorized.staff@example.com',
        'role' => StaffRole::Receptionist->value,
        'is_active' => true,
    ]))->toThrow(AuthorizationException::class);

    expect(fn () => app(UpdateStaffUser::class)->handle(
        $inactiveAdministrator,
        $staffUser,
        staffCompletionUpdateAttributes($staffUser, ['name' => 'Unauthorized Update']),
    ))->toThrow(AuthorizationException::class);

    expect(User::query()->where('email', 'unauthorized.staff@example.com')->exists())->toBeFalse()
        ->and($staffUser->fresh()->name)->not->toBe('Unauthorized Update')
        ->and(AuditLog::query()->count())->toBe(0);
});

it('changes effective permissions immediately without rewriting historical attribution', function () {
    $administrator = staffCompletionAdministrator();
    $staffUser = User::factory()->forRole(StaffRole::Receptionist)->create([
        'name' => 'Historical Staff Member',
    ]);
    $historicalAudit = AuditLog::factory()->create([
        'actor_id' => $staffUser->id,
        'action' => AuditAction::PatientRegistered,
    ]);

    $updatedStaffUser = app(UpdateStaffUser::class)->handle(
        $administrator,
        $staffUser,
        staffCompletionUpdateAttributes($staffUser, ['role' => StaffRole::Doctor->value]),
    );

    expect($updatedStaffUser->hasPermission(StaffPermission::PatientsView))->toBeFalse()
        ->and($updatedStaffUser->hasPermission(StaffPermission::ConsultationsView))->toBeTrue()
        ->and($historicalAudit->fresh()->actor_id)->toBe($staffUser->id)
        ->and($historicalAudit->fresh()->actor->name)->toBe('Historical Staff Member')
        ->and($historicalAudit->fresh()->action)->toBe(AuditAction::PatientRegistered);
});

it('prevents deactivating or reassigning a Doctor with an in-progress Consultation', function (array $overrides, string $errorKey) {
    $administrator = staffCompletionAdministrator();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    Consultation::factory()->for($doctor, 'doctor')->create();

    expect(fn () => app(UpdateStaffUser::class)->handle(
        $administrator,
        $doctor,
        staffCompletionUpdateAttributes($doctor, $overrides),
    ))->toThrow(ValidationException::class);

    expect($doctor->fresh()->is_active)->toBeTrue()
        ->and($doctor->fresh()->role->slug)->toBe(StaffRole::Doctor->value)
        ->and(AuditLog::query()->where('action', AuditAction::StaffUpdated)->count())->toBe(0);

    try {
        app(UpdateStaffUser::class)->handle(
            $administrator,
            $doctor,
            staffCompletionUpdateAttributes($doctor, $overrides),
        );
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($errorKey);
    }
})->with([
    'deactivation' => [['is_active' => false], 'is_active'],
    'role reassignment' => [['role' => StaffRole::Receptionist->value], 'role'],
]);

it('prevents deactivating a Nurse who owns an in-progress preparation', function () {
    $administrator = staffCompletionAdministrator();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    PreProcedureReadiness::factory()->createAuthoritativePreparationFixture($decision, $nurse);

    expect(fn () => app(UpdateStaffUser::class)->handle(
        $administrator,
        $nurse,
        staffCompletionUpdateAttributes($nurse, ['is_active' => false]),
    ))->toThrow(ValidationException::class);

    expect($nurse->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::StaffUpdated)->count())->toBe(0);
});

it('prevents reassigning a Nurse who owns an active recovery episode', function () {
    $administrator = staffCompletionAdministrator();
    [$procedureRecord, $nurse] = staffCompletionProcedureReadyForRecovery();
    RecoveryEpisode::factory()->createAuthoritativeRecoveryFixture($procedureRecord, $nurse);

    expect(fn () => app(UpdateStaffUser::class)->handle(
        $administrator,
        $nurse,
        staffCompletionUpdateAttributes($nurse, ['role' => StaffRole::Management->value]),
    ))->toThrow(ValidationException::class);

    expect($nurse->fresh()->role->slug)->toBe(StaffRole::Nurse->value)
        ->and(AuditLog::query()->where('action', AuditAction::StaffUpdated)->count())->toBe(0);
});

it('preserves financial clinical nursing audit and completed Visit history after staff lifecycle changes', function () {
    $administrator = staffCompletionAdministrator();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create(['name' => 'Historical Doctor']);
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create(['name' => 'Historical Nurse']);
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create(['name' => 'Historical Cashier']);
    $management = User::factory()->forRole(StaffRole::Management)->create(['name' => 'Historical Manager']);

    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $checkIn = $consultation->visit->checkIn;
    $receptionist = $checkIn->checkedInBy;
    $decision = ProcedureDecision::factory()
        ->for($consultation)
        ->procedureRequired()
        ->createAuthoritativeDecisionFixture();
    $preparation = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);
    $procedureRecord = ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($decision, $preparation, $doctor);
    $recovery = RecoveryEpisode::factory()
        ->createAuthoritativeRecoveryFixture($procedureRecord, $nurse);
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
        'assessment_note' => null,
    ]);
    $discharge = RecoveryDischarge::factory()
        ->createAuthoritativeDischargeFixture($recovery->refresh(), $nurse);

    $bill = Bill::factory()->has(BillItem::factory(), 'items')->create();
    $payment = Payment::factory()->for($bill)->create([
        'recorded_by_user_id' => $accountant->id,
    ]);
    $receipt = Receipt::factory()->for($payment)->create();
    $historicalAudit = AuditLog::factory()->create([
        'actor_id' => $management->id,
        'action' => AuditAction::StaffUpdated,
    ]);

    app(UpdateStaffUser::class)->handle(
        $administrator,
        $doctor,
        staffCompletionUpdateAttributes($doctor, ['role' => StaffRole::Receptionist->value]),
    );
    app(UpdateStaffUser::class)->handle(
        $administrator,
        $nurse,
        staffCompletionUpdateAttributes($nurse, [
            'role' => StaffRole::Receptionist->value,
            'is_active' => false,
        ]),
    );
    app(UpdateStaffUser::class)->handle(
        $administrator,
        $accountant,
        staffCompletionUpdateAttributes($accountant, ['is_active' => false]),
    );
    app(UpdateStaffUser::class)->handle(
        $administrator,
        $receptionist,
        staffCompletionUpdateAttributes($receptionist, ['is_active' => false]),
    );
    app(UpdateStaffUser::class)->handle(
        $administrator,
        $management,
        staffCompletionUpdateAttributes($management, ['is_active' => false]),
    );

    expect($consultation->fresh()->doctor->is($doctor))->toBeTrue()
        ->and($decision->fresh()->doctor->is($doctor))->toBeTrue()
        ->and($procedureRecord->fresh()->doctor->is($doctor))->toBeTrue()
        ->and($preparation->fresh()->nurse->is($nurse))->toBeTrue()
        ->and($recovery->fresh()->nurse->is($nurse))->toBeTrue()
        ->and($discharge->fresh()->dischargedBy->is($nurse))->toBeTrue()
        ->and($checkIn->fresh()->checkedInBy->is($receptionist))->toBeTrue()
        ->and($payment->fresh()->recordedBy->is($accountant))->toBeTrue()
        ->and($receipt->fresh()->payment->recordedBy->is($accountant))->toBeTrue()
        ->and($historicalAudit->fresh()->actor->is($management))->toBeTrue();

    $activeReceptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $this->actingAs($activeReceptionist)
        ->get(route('patients.show', $consultation->visit->patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.visitNumber', $consultation->visit->visit_number)
            ->where('visitHistory.data.0.status.value', 'completed')
            ->where('visitHistory.data.0.nextStep', 'Discharged / Completed')
        );
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array{name: string, email: string, role: string, is_active: bool}
 */
function staffCompletionUpdateAttributes(User $staffUser, array $overrides = []): array
{
    return [
        'name' => (string) ($overrides['name'] ?? $staffUser->name),
        'email' => (string) ($overrides['email'] ?? $staffUser->email),
        'role' => (string) ($overrides['role'] ?? $staffUser->role->slug),
        'is_active' => (bool) ($overrides['is_active'] ?? $staffUser->is_active),
    ];
}

function staffCompletionAdministrator(): User
{
    return User::factory()->forRole(StaffRole::Administrator)->create();
}

/** @return array{0: ProcedureRecord, 1: User} */
function staffCompletionProcedureReadyForRecovery(): array
{
    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $preparation = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);
    $procedureRecord = ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($decision, $preparation);

    return [$procedureRecord, $nurse];
}
