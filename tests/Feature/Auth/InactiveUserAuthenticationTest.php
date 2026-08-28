<?php

use App\Models\User;

it('rejects valid credentials for an inactive staff user', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('continues to authenticate an active staff user', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('terminates an authenticated session after the staff account is deactivated', function () {
    $user = User::factory()->create();
    $user->is_active = false;
    $user->save();

    $response = $this->actingAs($user)
        ->withSession(['checkpoint-marker' => 'must-be-invalidated'])
        ->get(route('dashboard'));

    $response
        ->assertRedirect(route('login'))
        ->assertSessionHas('status')
        ->assertSessionMissing('checkpoint-marker');
    $this->assertGuest();
});
