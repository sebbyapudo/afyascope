<?php

use App\Actions\Staff\UpdateStaffUser;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\StaffRole;
use Illuminate\Validation\ValidationException;

it('rejects deactivating the last active Administrator at the write boundary', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    expect(fn () => app(UpdateStaffUser::class)->handle($administrator, $administrator, staffUpdateAttributes(
        $administrator,
        ['is_active' => false],
    )))->toThrow(ValidationException::class);

    expect($administrator->fresh()->is_active)->toBeTrue()
        ->and($administrator->fresh()->role->slug)->toBe(StaffRole::Administrator->value)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects reassigning the last active Administrator at the write boundary', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    expect(fn () => app(UpdateStaffUser::class)->handle($administrator, $administrator, staffUpdateAttributes(
        $administrator,
        ['role' => StaffRole::Doctor->value],
    )))->toThrow(ValidationException::class);

    expect($administrator->fresh()->is_active)->toBeTrue()
        ->and($administrator->fresh()->role->slug)->toBe(StaffRole::Administrator->value)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('allows deactivating an Administrator when another active Administrator exists', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $actor = User::factory()->forRole(StaffRole::Administrator)->create();

    $updatedAdministrator = app(UpdateStaffUser::class)->handle($actor, $administrator, staffUpdateAttributes(
        $administrator,
        ['is_active' => false],
    ));

    expect($updatedAdministrator->is_active)->toBeFalse()
        ->and($updatedAdministrator->role->slug)->toBe(StaffRole::Administrator->value);

    $auditLog = AuditLog::query()->sole();
    expect($auditLog->actor->is($actor))->toBeTrue()
        ->and($auditLog->action)->toBe(AuditAction::StaffUpdated)
        ->and($auditLog->before_values)->toBe(['is_active' => true])
        ->and($auditLog->after_values)->toBe(['is_active' => false]);
});

it('allows reassigning an Administrator when another active Administrator exists', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $actor = User::factory()->forRole(StaffRole::Administrator)->create();

    $updatedAdministrator = app(UpdateStaffUser::class)->handle($actor, $administrator, staffUpdateAttributes(
        $administrator,
        ['role' => StaffRole::Doctor->value],
    ));

    expect($updatedAdministrator->is_active)->toBeTrue()
        ->and($updatedAdministrator->role->slug)->toBe(StaffRole::Doctor->value);

    $auditLog = AuditLog::query()->sole();
    $beforeValues = $auditLog->before_values;
    $afterValues = $auditLog->after_values;
    expect($auditLog->actor->is($actor))->toBeTrue()
        ->and($beforeValues)->toHaveKey('role.slug', StaffRole::Administrator->value)
        ->and($beforeValues)->toHaveKey('role.name', StaffRole::Administrator->displayName())
        ->and($afterValues)->toHaveKey('role.slug', StaffRole::Doctor->value)
        ->and($afterValues)->toHaveKey('role.name', StaffRole::Doctor->displayName());
});

it('audits staff name and email changes in one meaningful update record', function () {
    $actor = User::factory()->forRole(StaffRole::Administrator)->create();
    $staffUser = User::factory()->create([
        'name' => 'Previous Name',
        'email' => 'previous@example.com',
    ]);

    app(UpdateStaffUser::class)->handle($actor, $staffUser, staffUpdateAttributes(
        $staffUser,
        [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ],
    ));

    $auditLog = AuditLog::query()->sole();
    expect($auditLog->subject->is($staffUser))->toBeTrue()
        ->and($auditLog->before_values)->toBe([
            'name' => 'Previous Name',
            'email' => 'previous@example.com',
        ])
        ->and($auditLog->after_values)->toBe([
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
});

it('audits reactivating an inactive staff account', function () {
    $actor = User::factory()->forRole(StaffRole::Administrator)->create();
    $staffUser = User::factory()->inactive()->create();

    app(UpdateStaffUser::class)->handle($actor, $staffUser, staffUpdateAttributes(
        $staffUser,
        ['is_active' => true],
    ));

    $auditLog = AuditLog::query()->sole();
    expect($auditLog->before_values)->toBe(['is_active' => false])
        ->and($auditLog->after_values)->toBe(['is_active' => true]);
});

it('does not create audit history for a no-op staff update', function () {
    $actor = User::factory()->forRole(StaffRole::Administrator)->create();
    $staffUser = User::factory()->create();

    app(UpdateStaffUser::class)->handle($actor, $staffUser, staffUpdateAttributes($staffUser));

    expect(AuditLog::query()->count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array{name: string, email: string, role: string, is_active: bool}
 */
function staffUpdateAttributes(User $staffUser, array $overrides = []): array
{
    return [
        'name' => $overrides['name'] ?? $staffUser->name,
        'email' => $overrides['email'] ?? $staffUser->email,
        'role' => $overrides['role'] ?? $staffUser->role->slug,
        'is_active' => $overrides['is_active'] ?? $staffUser->is_active,
    ];
}
