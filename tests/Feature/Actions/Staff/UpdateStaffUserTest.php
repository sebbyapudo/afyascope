<?php

use App\Actions\Staff\UpdateStaffUser;
use App\Models\User;
use App\StaffRole;
use Illuminate\Validation\ValidationException;

it('rejects deactivating the last active Administrator at the write boundary', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    expect(fn () => app(UpdateStaffUser::class)->handle($administrator, staffUpdateAttributes(
        $administrator,
        ['is_active' => false],
    )))->toThrow(ValidationException::class);

    expect($administrator->fresh()->is_active)->toBeTrue()
        ->and($administrator->fresh()->role->slug)->toBe(StaffRole::Administrator->value);
});

it('rejects reassigning the last active Administrator at the write boundary', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    expect(fn () => app(UpdateStaffUser::class)->handle($administrator, staffUpdateAttributes(
        $administrator,
        ['role' => StaffRole::Doctor->value],
    )))->toThrow(ValidationException::class);

    expect($administrator->fresh()->is_active)->toBeTrue()
        ->and($administrator->fresh()->role->slug)->toBe(StaffRole::Administrator->value);
});

it('allows deactivating an Administrator when another active Administrator exists', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    User::factory()->forRole(StaffRole::Administrator)->create();

    $updatedAdministrator = app(UpdateStaffUser::class)->handle($administrator, staffUpdateAttributes(
        $administrator,
        ['is_active' => false],
    ));

    expect($updatedAdministrator->is_active)->toBeFalse()
        ->and($updatedAdministrator->role->slug)->toBe(StaffRole::Administrator->value);
});

it('allows reassigning an Administrator when another active Administrator exists', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    User::factory()->forRole(StaffRole::Administrator)->create();

    $updatedAdministrator = app(UpdateStaffUser::class)->handle($administrator, staffUpdateAttributes(
        $administrator,
        ['role' => StaffRole::Doctor->value],
    ));

    expect($updatedAdministrator->is_active)->toBeTrue()
        ->and($updatedAdministrator->role->slug)->toBe(StaffRole::Doctor->value);
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
