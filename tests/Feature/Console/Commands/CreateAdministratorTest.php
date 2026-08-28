<?php

use App\Models\Role;
use App\Models\User;
use App\StaffRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

it('creates the first active Administrator with normalized credentials and a secure password', function () {
    $password = 'Secure-bootstrap-password-42!';

    $this->artisan('afyascope:create-administrator')
        ->expectsQuestion('Name', '  Initial Administrator  ')
        ->expectsQuestion('Email address', '  INITIAL.ADMIN@EXAMPLE.COM  ')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->expectsOutputToContain('Administrator account created for initial.admin@example.com.')
        ->doesntExpectOutputToContain($password)
        ->assertExitCode(Command::SUCCESS);

    $administrator = User::query()->where('email', 'initial.admin@example.com')->sole();

    expect($administrator->name)->toBe('Initial Administrator')
        ->and($administrator->is_active)->toBeTrue()
        ->and($administrator->role->slug)->toBe(StaffRole::Administrator->value)
        ->and(Hash::check($password, $administrator->password))->toBeTrue()
        ->and($administrator->toArray())->not->toHaveKey('password');
});

it('fails safely when the canonical Administrator role is missing', function () {
    Role::query()->where('slug', StaffRole::Administrator->value)->delete();

    $this->artisan('afyascope:create-administrator')
        ->expectsOutputToContain('canonical Administrator role is missing')
        ->assertExitCode(Command::FAILURE);

    expect(User::query()->count())->toBe(0);
});

it('rejects an email already assigned to another staff user after normalization', function () {
    User::factory()->create(['email' => 'existing.staff@example.com']);
    $password = 'Secure-bootstrap-password-42!';

    $this->artisan('afyascope:create-administrator')
        ->expectsQuestion('Name', 'Initial Administrator')
        ->expectsQuestion('Email address', 'EXISTING.STAFF@EXAMPLE.COM')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->doesntExpectOutputToContain($password)
        ->assertExitCode(Command::FAILURE);

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'existing.staff@example.com')->sole()->role->slug)
        ->toBe(StaffRole::Receptionist->value);
});

it('refuses repeated bootstrap while an active Administrator exists', function () {
    User::factory()->forRole(StaffRole::Administrator)->create();

    $this->artisan('afyascope:create-administrator')
        ->expectsOutputToContain('An active Administrator already exists')
        ->assertExitCode(Command::FAILURE);

    expect(User::query()->count())->toBe(1);
});

it('uses the application password rules', function () {
    $password = 'short';

    $this->artisan('afyascope:create-administrator')
        ->expectsQuestion('Name', 'Initial Administrator')
        ->expectsQuestion('Email address', 'initial.admin@example.com')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->doesntExpectOutputToContain($password)
        ->assertExitCode(Command::FAILURE);

    $this->assertDatabaseMissing('users', ['email' => 'initial.admin@example.com']);
});
