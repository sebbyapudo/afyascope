<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the password-reset request screen', function () {
    $this->get(route('password.request'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/forgot-password'));
});

it('sends a secure password-reset notification to an existing staff user', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->post(route('password.email'), ['email' => $user->email]);

    $response->assertRedirect();
    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the password setup screen from a valid reset notification', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get(route('password.reset', [
            'token' => $notification->token,
            'email' => $user->email,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('auth/reset-password')
            ->where('email', $user->email)
            ->where('token', $notification->token)
        );

        return true;
    });
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('login'));
        expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();

        return true;
    });
});

it('rejects an invalid reset token without changing the password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
