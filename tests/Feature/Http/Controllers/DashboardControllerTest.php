<?php

use App\Models\Permission;
use App\Models\User;
use App\StaffPermission;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

test('unauthenticated users are redirected from the dashboard to sign in', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('all six staff roles can access the protected dashboard', function (StaffRole $role) {
    $user = User::factory()->forRole($role)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
})->with(StaffRole::cases());

test('an authenticated user without dashboard permission is forbidden by direct request', function () {
    $user = User::factory()->forRole(StaffRole::Receptionist)->create();
    $dashboardPermission = Permission::query()
        ->where('slug', StaffPermission::DashboardView->value)
        ->sole();

    $user->role->permissions()->detach($dashboardPermission);
    $user->unsetRelation('role');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});
