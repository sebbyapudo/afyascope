<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('redirects guests to sign in before updating a password', function () {
    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertRedirect(route('login'));
});

it('updates the authenticated staff user password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an incorrect current password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'incorrect-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertSessionHasErrorsIn('updatePassword', 'current_password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('rejects a password without matching confirmation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertSessionHasErrorsIn('updatePassword', 'password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
