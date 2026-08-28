<?php

use App\Models\Role;
use App\Models\User;
use App\StaffRole;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('allows an Administrator to list staff with minimal identity role and status data', function () {
    $administrator = checkpointAdministrator();
    $staffUser = User::factory()->forRole(StaffRole::Nurse)->create([
        'name' => 'Staff Member',
        'email' => 'staff.member@example.com',
    ]);

    $this->actingAs($administrator)
        ->get(route('staff.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/index')
            ->has('staffUsers', 2)
            ->where('staffUsers.1', [
                'id' => $staffUser->id,
                'name' => 'Staff Member',
                'email' => 'staff.member@example.com',
                'role' => [
                    'slug' => StaffRole::Nurse->value,
                    'displayName' => StaffRole::Nurse->displayName(),
                ],
                'isActive' => true,
            ])
        );
});

it('allows an Administrator to access the create page with exactly six canonical roles', function () {
    $administrator = checkpointAdministrator();

    $this->actingAs($administrator)
        ->get(route('staff.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/create')
            ->where('roles', canonicalRoleOptions())
        );
});

it('creates a staff account with one canonical role and sends a password setup notification', function () {
    Notification::fake();
    $administrator = checkpointAdministrator();

    $response = $this->actingAs($administrator)->post(route('staff.store'), validStaffPayload([
        'name' => '  New Staff Member  ',
        'email' => 'NEW.STAFF@EXAMPLE.COM',
        'role' => StaffRole::Doctor->value,
        'password' => 'administrator-selected-password',
    ]));

    $response
        ->assertRedirect(route('staff.index'))
        ->assertSessionHas('status')
        ->assertSessionMissing('password');
    $staffUser = User::query()->where('email', 'new.staff@example.com')->sole();
    expect($staffUser->name)->toBe('New Staff Member')
        ->and($staffUser->is_active)->toBeTrue()
        ->and($staffUser->role->slug)->toBe(StaffRole::Doctor->value)
        ->and(method_exists($staffUser, 'roles'))->toBeFalse()
        ->and(Hash::check('administrator-selected-password', $staffUser->password))->toBeFalse()
        ->and($staffUser->toArray())->not->toHaveKeys(['password', 'remember_token']);
    $this->assertDatabaseHas('password_reset_tokens', ['email' => 'new.staff@example.com']);
    Notification::assertSentTo(
        $staffUser,
        ResetPassword::class,
        fn (ResetPassword $notification): bool => filled($notification->token),
    );
});

it('creates an inactive staff account when that status is selected', function () {
    Notification::fake();
    $administrator = checkpointAdministrator();

    $this->actingAs($administrator)
        ->post(route('staff.store'), validStaffPayload([
            'email' => 'inactive.new@example.com',
            'is_active' => false,
        ]))
        ->assertRedirect(route('staff.index'));

    $staffUser = User::query()->where('email', 'inactive.new@example.com')->sole();
    expect($staffUser->is_active)->toBeFalse();
    Notification::assertSentTo($staffUser, ResetPassword::class);
});

it('rejects a noncanonical role even when a matching arbitrary database role ID is submitted', function () {
    $administrator = checkpointAdministrator();
    $customRole = Role::query()->create([
        'slug' => 'custom-role',
        'name' => 'Custom role',
    ]);

    $this->actingAs($administrator)
        ->post(route('staff.store'), validStaffPayload([
            'email' => 'invalid.role@example.com',
            'role' => 'custom-role',
            'role_id' => $customRole->id,
        ]))
        ->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'invalid.role@example.com']);
});

it('rejects a duplicate staff email after normalization', function () {
    $administrator = checkpointAdministrator();
    User::factory()->create(['email' => 'duplicate@example.com']);

    $this->actingAs($administrator)
        ->post(route('staff.store'), validStaffPayload([
            'email' => ' DUPLICATE@EXAMPLE.COM ',
        ]))
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'duplicate@example.com')->count())->toBe(1);
});

it('ignores unsupported privilege and password fields during staff creation', function () {
    Notification::fake();
    $administrator = checkpointAdministrator();
    $administratorRoleId = Role::query()
        ->where('slug', StaffRole::Administrator->value)
        ->soleValue('id');

    $this->actingAs($administrator)
        ->post(route('staff.store'), validStaffPayload([
            'email' => 'guarded.fields@example.com',
            'role' => StaffRole::Receptionist->value,
            'role_id' => $administratorRoleId,
            'permission_id' => 1,
            'permissions' => ['*'],
            'is_super_admin' => true,
            'password' => 'known-password',
        ]))
        ->assertRedirect(route('staff.index'));

    $staffUser = User::query()->where('email', 'guarded.fields@example.com')->sole();
    expect($staffUser->role->slug)->toBe(StaffRole::Receptionist->value)
        ->and(Hash::check('known-password', $staffUser->password))->toBeFalse()
        ->and(array_key_exists('permission_id', $staffUser->getAttributes()))->toBeFalse()
        ->and(array_key_exists('is_super_admin', $staffUser->getAttributes()))->toBeFalse();
});

it('allows an Administrator to access the edit page', function () {
    $administrator = checkpointAdministrator();
    $staffUser = User::factory()->forRole(StaffRole::Accountant)->create();

    $this->actingAs($administrator)
        ->get(route('staff.edit', $staffUser))
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/edit')
            ->where('staffUser.id', $staffUser->id)
            ->where('staffUser.role.slug', StaffRole::Accountant->value)
            ->where('staffUser.isActive', true)
            ->where('roles', canonicalRoleOptions())
        );
});

it('updates staff identity and canonical role', function () {
    $administrator = checkpointAdministrator();
    $staffUser = User::factory()->forRole(StaffRole::Receptionist)->create();

    $this->actingAs($administrator)
        ->put(route('staff.update', $staffUser), validStaffPayload([
            'name' => 'Updated Staff Name',
            'email' => 'UPDATED.STAFF@EXAMPLE.COM',
            'role' => StaffRole::Nurse->value,
        ]))
        ->assertRedirect(route('staff.index'));

    $staffUser->refresh();
    expect($staffUser->name)->toBe('Updated Staff Name')
        ->and($staffUser->email)->toBe('updated.staff@example.com')
        ->and($staffUser->role->slug)->toBe(StaffRole::Nurse->value)
        ->and($staffUser->is_active)->toBeTrue();
});

it('deactivates a staff account without deleting it', function () {
    $administrator = checkpointAdministrator();
    $staffUser = User::factory()->create();

    $this->actingAs($administrator)
        ->put(route('staff.update', $staffUser), validStaffPayload([
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'is_active' => false,
        ]))
        ->assertRedirect(route('staff.index'));

    $this->assertModelExists($staffUser);
    expect($staffUser->fresh()->is_active)->toBeFalse();
});

it('reactivates an inactive staff account', function () {
    $administrator = checkpointAdministrator();
    $staffUser = User::factory()->inactive()->create();

    $this->actingAs($administrator)
        ->put(route('staff.update', $staffUser), validStaffPayload([
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'is_active' => true,
        ]))
        ->assertRedirect(route('staff.index'));

    expect($staffUser->fresh()->is_active)->toBeTrue();
});

it('returns validation errors when staff administration would remove the last active Administrator', function (array $overrides, string $errorKey) {
    $administrator = checkpointAdministrator();

    $this->actingAs($administrator)
        ->put(route('staff.update', $administrator), validStaffPayload([
            'name' => $administrator->name,
            'email' => $administrator->email,
            ...$overrides,
        ]))
        ->assertSessionHasErrors($errorKey);

    expect($administrator->fresh()->is_active)->toBeTrue()
        ->and($administrator->fresh()->role->slug)->toBe(StaffRole::Administrator->value);
})->with([
    'deactivation' => [['is_active' => false], 'is_active'],
    'role reassignment' => [['role' => StaffRole::Doctor->value], 'role'],
]);

it('forbids every non-Administrator role from staff administration', function (StaffRole $staffRole) {
    $actor = User::factory()->forRole($staffRole)->create();
    $staffUser = User::factory()->create();

    $this->actingAs($actor)->get(route('staff.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('staff.create'))->assertForbidden();
    $this->actingAs($actor)->get(route('staff.edit', $staffUser))->assertForbidden();
    $this->actingAs($actor)
        ->post(route('staff.store'), validStaffPayload(['email' => 'forbidden.create@example.com']))
        ->assertForbidden();
    $this->actingAs($actor)
        ->put(route('staff.update', $staffUser), validStaffPayload([
            'name' => $staffUser->name,
            'email' => $staffUser->email,
        ]))
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'forbidden.create@example.com']);
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Management,
]);

it('redirects guests away from staff administration', function () {
    $staffUser = User::factory()->create();

    $this->get(route('staff.index'))->assertRedirect(route('login'));
    $this->post(route('staff.store'), validStaffPayload())->assertRedirect(route('login'));
    $this->put(route('staff.update', $staffUser), validStaffPayload())->assertRedirect(route('login'));
});

it('exposes no staff deletion or mutable role routes', function () {
    $routeCollection = collect(Route::getRoutes()->getRoutes());

    expect(Route::has('staff.destroy'))->toBeFalse()
        ->and(Route::has('roles.store'))->toBeFalse()
        ->and(Route::has('roles.update'))->toBeFalse()
        ->and(Route::has('roles.destroy'))->toBeFalse()
        ->and($routeCollection->contains(
            fn (Illuminate\Routing\Route $route): bool => in_array('DELETE', $route->methods(), true)
                && str_starts_with($route->uri(), 'staff'),
        ))->toBeFalse()
        ->and($routeCollection->contains(
            fn (Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'roles'),
        ))->toBeFalse();
});

function checkpointAdministrator(): User
{
    return User::factory()->forRole(StaffRole::Administrator)->create([
        'name' => 'Admin Actor',
        'email' => fake()->unique()->safeEmail(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validStaffPayload(array $overrides = []): array
{
    return [
        'name' => 'New Staff Member',
        'email' => fake()->unique()->safeEmail(),
        'role' => StaffRole::Receptionist->value,
        'is_active' => true,
        ...$overrides,
    ];
}

/**
 * @return list<array{value: string, label: string}>
 */
function canonicalRoleOptions(): array
{
    return array_map(
        static fn (StaffRole $staffRole): array => [
            'value' => $staffRole->value,
            'label' => $staffRole->displayName(),
        ],
        StaffRole::cases(),
    );
}
