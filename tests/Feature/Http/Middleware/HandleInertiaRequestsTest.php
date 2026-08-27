<?php

use App\Models\User;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated Inertia responses share only sanitized identity role and capabilities', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $administrator->id,
                'name' => $administrator->name,
                'email' => $administrator->email,
            ])
            ->where('auth.role', [
                'slug' => 'administrator',
                'displayName' => 'Administrator',
            ])
            ->where('auth.capabilities', [
                'viewDashboard' => true,
                'viewUsers' => true,
                'manageUsers' => true,
                'viewRoles' => true,
                'viewAudit' => true,
            ])
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.created_at')
            ->missing('auth.user.updated_at')
        );
});

test('guest Inertia responses share no staff identity or capabilities', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('auth.role', null)
            ->where('auth.capabilities', [
                'viewDashboard' => false,
                'viewUsers' => false,
                'manageUsers' => false,
                'viewRoles' => false,
                'viewAudit' => false,
            ])
        );
});
