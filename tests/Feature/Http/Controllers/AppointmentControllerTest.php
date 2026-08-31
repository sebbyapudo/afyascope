<?php

use App\AppointmentStatus;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('allows a Receptionist to access every delivered Appointment page', function () {
    $receptionist = appointmentManagementReceptionist();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();

    $this->actingAs($receptionist)->get(route('appointments.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('appointments/index'));
    $this->actingAs($receptionist)->get(route('patients.appointments.create', $patient))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('appointments/create')
            ->where('patient.id', $patient->id)
            ->where('patient.patientNumber', $patient->patient_number));
    $this->actingAs($receptionist)->get(route('appointments.show', $appointment))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('appointments/show'));
    $this->actingAs($receptionist)->get(route('appointments.edit', $appointment))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('appointments/edit'));
});

it('forbids every non-Receptionist role from direct Appointment URLs', function (StaffRole $staffRole) {
    $actor = User::factory()->forRole($staffRole)->create();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $scheduledAt = now()->addDay()->toIso8601String();

    $this->actingAs($actor)->get(route('appointments.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('appointments.show', $appointment))->assertForbidden();
    $this->actingAs($actor)->get(route('appointments.edit', $appointment))->assertForbidden();
    $this->actingAs($actor)->get(route('patients.appointments.create', $patient))->assertForbidden();
    $this->actingAs($actor)->post(route('patients.appointments.store', $patient), ['scheduled_at' => $scheduledAt])->assertForbidden();
    $this->actingAs($actor)->put(route('appointments.update', $appointment), ['scheduled_at' => $scheduledAt])->assertForbidden();
    $this->actingAs($actor)->post(route('appointments.cancel', $appointment))->assertForbidden();
    $this->actingAs($actor)->post(route('appointments.no-show', $appointment))->assertForbidden();
})->with([
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('redirects guests away from every Appointment endpoint', function () {
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $scheduledAt = now()->addDay()->toIso8601String();

    $this->get(route('appointments.index'))->assertRedirect(route('login'));
    $this->get(route('appointments.show', $appointment))->assertRedirect(route('login'));
    $this->get(route('appointments.edit', $appointment))->assertRedirect(route('login'));
    $this->get(route('patients.appointments.create', $patient))->assertRedirect(route('login'));
    $this->post(route('patients.appointments.store', $patient), ['scheduled_at' => $scheduledAt])->assertRedirect(route('login'));
    $this->put(route('appointments.update', $appointment), ['scheduled_at' => $scheduledAt])->assertRedirect(route('login'));
    $this->post(route('appointments.cancel', $appointment))->assertRedirect(route('login'));
    $this->post(route('appointments.no-show', $appointment))->assertRedirect(route('login'));
});

it('renders a deterministic paginated Appointment registry and preserves filters', function () {
    $receptionist = appointmentManagementReceptionist();
    $patient = Patient::factory()->create(['first_name' => 'Registry']);
    Appointment::factory()->for($patient)->count(16)->sequence(
        fn ($sequence): array => ['scheduled_at' => now()->addDays($sequence->index + 1)],
    )->create();

    $this->actingAs($receptionist)
        ->get(route('appointments.index', ['q' => ' Registry ', 'status' => 'scheduled', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->component('appointments/index')
            ->where('filters.q', 'Registry')
            ->where('filters.status', 'scheduled')
            ->has('appointments.data', 1)
            ->where('appointments.pagination.currentPage', 2)
            ->where('appointments.pagination.total', 16)
            ->where('appointments.pagination.lastPage', 2));
});

it('searches by Appointment reference Patient reference and partial Patient names', function () {
    $receptionist = appointmentManagementReceptionist();
    $targetPatient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
    ]);
    $targetAppointment = Appointment::factory()->for($targetPatient)->create();
    Appointment::factory()->create();

    foreach ([$targetAppointment->appointment_number, $targetPatient->patient_number, 'Ami', 'Wanj', 'Kam'] as $search) {
        $this->actingAs($receptionist)->get(route('appointments.index', ['q' => $search]))
            ->assertInertia(fn (Assert $page) => $page->has('appointments.data', 1)
                ->where('appointments.data.0.id', $targetAppointment->id)
                ->where('filters.q', $search));
    }
});

it('filters Appointments by date and exact status while treating wildcards literally', function () {
    Carbon::withTestNow('2026-08-31 08:00:00', function (): void {
        $receptionist = appointmentManagementReceptionist();
        $target = Appointment::factory()->create(['scheduled_at' => '2026-09-02 10:00:00']);
        $cancelled = Appointment::factory()->create(['scheduled_at' => '2026-09-02 11:00:00']);
        $cancelled->forceFill(['status' => AppointmentStatus::Cancelled])->save();
        Appointment::factory()->create(['scheduled_at' => '2026-09-03 10:00:00']);

        $this->actingAs($receptionist)->get(route('appointments.index', ['date' => '2026-09-02', 'status' => 'scheduled']))
            ->assertInertia(fn (Assert $page) => $page->has('appointments.data', 1)
                ->where('appointments.data.0.id', $target->id)
                ->where('filters.date', '2026-09-02')
                ->where('filters.status', 'scheduled'));
        $this->actingAs($receptionist)->get(route('appointments.index', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('appointments.data', 0)->where('filters.q', '%'));
    });
});

it('schedules an Appointment for an existing Patient without creating a Visit', function () {
    Carbon::withTestNow('2026-08-31 08:00:00', function (): void {
        $receptionist = appointmentManagementReceptionist();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($receptionist)->post(route('patients.appointments.store', $patient), [
            'scheduled_at' => '2026-09-01T10:30:00Z',
        ]);

        $appointment = Appointment::query()->sole();
        $response->assertRedirect(route('appointments.show', $appointment))
            ->assertSessionHas('status', "Appointment {$appointment->appointment_number} was scheduled.");
        expect($appointment->patient->is($patient))->toBeTrue()
            ->and($appointment->appointment_number)->toMatch('/^APT-\d{6,}$/')
            ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
            ->and($appointment->scheduled_at->toIso8601String())->toBe('2026-09-01T10:30:00+00:00')
            ->and(Visit::query()->count())->toBe(0)
            ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::AppointmentCreated);
    });
});

it('rejects missing past and server-controlled Appointment input', function (array $payload, string $errorKey) {
    Carbon::withTestNow('2026-08-31 08:00:00', function () use ($payload, $errorKey): void {
        $patient = Patient::factory()->create();

        $this->actingAs(appointmentManagementReceptionist())
            ->post(route('patients.appointments.store', $patient), $payload)
            ->assertSessionHasErrors($errorKey);
        expect(Appointment::query()->count())->toBe(0)->and(AuditLog::query()->count())->toBe(0);
    });
})->with([
    'missing schedule' => [[], 'scheduled_at'],
    'past schedule' => [['scheduled_at' => '2026-08-30T10:00:00Z'], 'scheduled_at'],
    'primary key' => [['scheduled_at' => '2026-09-01T10:00:00Z', 'id' => 999999], 'id'],
    'Patient foreign key' => [['scheduled_at' => '2026-09-01T10:00:00Z', 'patient_id' => 999999], 'patient_id'],
    'Appointment reference' => [['scheduled_at' => '2026-09-01T10:00:00Z', 'appointment_number' => 'APT-999999'], 'appointment_number'],
    'status' => [['scheduled_at' => '2026-09-01T10:00:00Z', 'status' => 'cancelled'], 'status'],
]);

it('reschedules a scheduled Appointment and audits only a meaningful change', function () {
    Carbon::withTestNow('2026-08-31 08:00:00', function (): void {
        $receptionist = appointmentManagementReceptionist();
        $appointment = Appointment::factory()->create(['scheduled_at' => '2026-09-01 10:00:00']);
        $appointmentNumber = $appointment->appointment_number;

        $this->actingAs($receptionist)->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-02T12:30:00Z',
        ])->assertRedirect(route('appointments.show', $appointment));

        $appointment->refresh();
        expect($appointment->scheduled_at->toIso8601String())->toBe('2026-09-02T12:30:00+00:00')
            ->and($appointment->appointment_number)->toBe($appointmentNumber)
            ->and(AuditLog::query()->where('action', AuditAction::AppointmentRescheduled)->count())->toBe(1);

        $this->actingAs($receptionist)->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-02T12:30:00Z',
        ])->assertRedirect(route('appointments.show', $appointment));
        expect(AuditLog::query()->where('action', AuditAction::AppointmentRescheduled)->count())->toBe(1);
    });
});

it('cancels a scheduled Appointment without deleting it or duplicating audit events', function () {
    $receptionist = appointmentManagementReceptionist();
    $appointment = Appointment::factory()->create();

    $this->actingAs($receptionist)->post(route('appointments.cancel', $appointment))->assertRedirect(route('appointments.show', $appointment));
    $this->actingAs($receptionist)->post(route('appointments.cancel', $appointment))->assertRedirect(route('appointments.show', $appointment));

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(Appointment::query()->whereKey($appointment)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::AppointmentCancelled)->count())->toBe(1);
});

it('marks a scheduled Appointment as a no-show without deleting it', function () {
    $receptionist = appointmentManagementReceptionist();
    $appointment = Appointment::factory()->create();

    $this->actingAs($receptionist)->post(route('appointments.no-show', $appointment))->assertRedirect(route('appointments.show', $appointment));

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and(Appointment::query()->whereKey($appointment)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::AppointmentNoShow)->count())->toBe(1);
});

it('prevents rescheduling or changing a terminal Appointment state', function (AppointmentStatus $terminalStatus) {
    Carbon::withTestNow('2026-08-31 08:00:00', function () use ($terminalStatus): void {
        $receptionist = appointmentManagementReceptionist();
        $appointment = Appointment::factory()->create();
        $appointment->forceFill(['status' => $terminalStatus])->save();

        $this->actingAs($receptionist)->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-02T12:30:00Z',
        ])->assertSessionHasErrors('scheduled_at');
        $this->actingAs($receptionist)->post(route(
            $terminalStatus === AppointmentStatus::Cancelled ? 'appointments.no-show' : 'appointments.cancel',
            $appointment,
        ))->assertSessionHasErrors('status');

        expect($appointment->refresh()->status)->toBe($terminalStatus)->and(AuditLog::query()->count())->toBe(0);
    });
})->with([AppointmentStatus::Cancelled, AppointmentStatus::NoShow]);

it('integrates five nearest upcoming scheduled Appointments into the Patient profile', function () {
    Carbon::withTestNow('2026-08-31 08:00:00', function (): void {
        $receptionist = appointmentManagementReceptionist();
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $appointments = Appointment::factory()->for($patient)->count(6)->sequence(
            fn ($sequence): array => ['scheduled_at' => now()->addDays($sequence->index + 1)],
        )->create();
        Appointment::factory()->for($patient)->create(['scheduled_at' => now()->subDay()]);
        $cancelled = Appointment::factory()->for($patient)->create(['scheduled_at' => now()->addHours(2)]);
        $cancelled->forceFill(['status' => AppointmentStatus::Cancelled])->save();
        Appointment::factory()->for($otherPatient)->create(['scheduled_at' => now()->addHour()]);
        $expectedIds = $appointments->sortBy('scheduled_at')->take(5)->pluck('id')->values()->all();

        $this->actingAs($receptionist)->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page->has('upcomingAppointments.data', 5)
                ->where('upcomingAppointments.data', fn ($upcoming): bool => collect($upcoming)->pluck('id')->all() === $expectedIds)
                ->where('upcomingAppointments.pagination.total', 6)
                ->missing('patient.appointments'));
    });
});

it('shows only sanitized administrative Appointment data', function () {
    $receptionist = appointmentManagementReceptionist();
    $patient = Patient::factory()->create(['first_name' => 'Amina', 'middle_name' => 'Wanjiku', 'last_name' => 'Kamau']);
    $appointment = Appointment::factory()->for($patient)->create();

    $this->actingAs($receptionist)->get(route('appointments.show', $appointment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointment.id', $appointment->id)
            ->where('appointment.appointmentNumber', $appointment->appointment_number)
            ->where('appointment.patient.name', 'Amina Wanjiku Kamau')
            ->where('appointment.status', ['value' => 'scheduled', 'label' => 'Scheduled'])
            ->missing('appointment.note')
            ->missing('appointment.visit')
            ->missing('appointment.charges')
            ->missing('appointment.payments')
            ->missing('appointment.clearance')
            ->missing('appointment.clinical')
            ->missing('appointment.procedures')
            ->missing('appointment.nursing'));
});

it('exposes no Appointment deletion route', function () {
    $routeCollection = collect(Route::getRoutes()->getRoutes());

    expect(Route::has('appointments.destroy'))->toBeFalse()
        ->and($routeCollection->contains(fn (IlluminateRoute $route): bool => in_array('DELETE', $route->methods(), true)
            && str_starts_with($route->uri(), 'appointments')))->toBeFalse();
});

function appointmentManagementReceptionist(): User
{
    return User::factory()->forRole(StaffRole::Receptionist)->create();
}
