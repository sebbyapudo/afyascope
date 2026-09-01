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
                'viewPatients' => false,
                'createPatients' => false,
                'updatePatients' => false,
                'viewVisits' => false,
                'createVisits' => false,
                'viewAppointments' => false,
                'createAppointments' => false,
                'updateAppointments' => false,
                'viewBilling' => false,
                'createBilling' => false,
                'viewPayments' => false,
                'createPayments' => false,
                'viewClearance' => false,
                'createClearance' => false,
                'viewCheckIns' => false,
                'createCheckIns' => false,
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
                'viewPatients' => false,
                'createPatients' => false,
                'updatePatients' => false,
                'viewVisits' => false,
                'createVisits' => false,
                'viewAppointments' => false,
                'createAppointments' => false,
                'updateAppointments' => false,
                'viewBilling' => false,
                'createBilling' => false,
                'viewPayments' => false,
                'createPayments' => false,
                'viewClearance' => false,
                'createClearance' => false,
                'viewCheckIns' => false,
                'createCheckIns' => false,
            ])
        );
});

test('Receptionist Inertia responses expose only the Patient capabilities granted to the role', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();

    $this->actingAs($receptionist)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.viewPatients', true)
            ->where('auth.capabilities.createPatients', true)
            ->where('auth.capabilities.updatePatients', true)
            ->where('auth.capabilities.viewVisits', true)
            ->where('auth.capabilities.createVisits', true)
            ->where('auth.capabilities.viewAppointments', true)
            ->where('auth.capabilities.createAppointments', true)
            ->where('auth.capabilities.updateAppointments', true)
            ->where('auth.capabilities.viewUsers', false)
            ->where('auth.capabilities.viewAudit', false)
            ->where('auth.capabilities.viewBilling', false)
            ->where('auth.capabilities.createBilling', false)
            ->where('auth.capabilities.viewPayments', false)
            ->where('auth.capabilities.createPayments', false)
            ->where('auth.capabilities.viewClearance', false)
            ->where('auth.capabilities.createClearance', false)
            ->where('auth.capabilities.viewCheckIns', true)
            ->where('auth.capabilities.createCheckIns', true)
        );
});

test('Accountant Inertia responses expose only the billing capabilities granted to the role', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();

    $this->actingAs($accountant)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.capabilities.viewBilling', true)
            ->where('auth.capabilities.createBilling', true)
            ->where('auth.capabilities.viewPayments', true)
            ->where('auth.capabilities.createPayments', true)
            ->where('auth.capabilities.viewClearance', true)
            ->where('auth.capabilities.createClearance', true)
            ->where('auth.capabilities.viewCheckIns', false)
            ->where('auth.capabilities.createCheckIns', false)
            ->where('auth.capabilities.viewPatients', false)
            ->where('auth.capabilities.viewVisits', false)
            ->where('auth.capabilities.viewAppointments', false)
            ->where('auth.capabilities.viewUsers', false)
            ->where('auth.capabilities.viewAudit', false)
        );
});
