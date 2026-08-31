<?php

use App\AppointmentStatus;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('completes the Phase 2 Reception journey without duplicating identity records or audit events', function () {
    Carbon::withTestNow('2026-09-01 09:00:00', function (): void {
        $receptionist = phase2Receptionist();

        $this->actingAs($receptionist)
            ->post(route('patients.store'), phase2PatientPayload())
            ->assertRedirect();

        $patient = Patient::query()->sole();
        $patientNumber = $patient->patient_number;

        $this->actingAs($receptionist)
            ->get(route('patients.index', ['q' => $patientNumber]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('patients.data', 1)
                ->where('patients.data.0.id', $patient->id)
                ->where('patients.data.0.patientNumber', $patientNumber)
            );

        $updatedPatientPayload = phase2PatientPayload([
            'last_name' => 'Otieno',
            'phone' => '+254700000002',
        ]);

        $this->actingAs($receptionist)
            ->put(route('patients.update', $patient), $updatedPatientPayload)
            ->assertRedirect(route('patients.show', $patient));
        $this->actingAs($receptionist)
            ->put(route('patients.update', $patient), $updatedPatientPayload)
            ->assertRedirect(route('patients.show', $patient));

        $this->actingAs($receptionist)
            ->post(route('patients.appointments.store', $patient), [
                'scheduled_at' => '2026-09-10 10:00:00',
            ])
            ->assertRedirect();
        $rescheduledAppointment = Appointment::query()->latest('id')->firstOrFail();
        $rescheduledAppointmentNumber = $rescheduledAppointment->appointment_number;

        $this->actingAs($receptionist)
            ->put(route('appointments.update', $rescheduledAppointment), [
                'scheduled_at' => '2026-09-11 10:30:00',
            ])
            ->assertRedirect(route('appointments.show', $rescheduledAppointment));
        $this->actingAs($receptionist)
            ->put(route('appointments.update', $rescheduledAppointment), [
                'scheduled_at' => '2026-09-11 10:30:00',
            ])
            ->assertRedirect(route('appointments.show', $rescheduledAppointment));

        $this->actingAs($receptionist)
            ->post(route('patients.appointments.store', $patient), [
                'scheduled_at' => '2026-09-12 11:00:00',
            ])
            ->assertRedirect();
        $cancelledAppointment = Appointment::query()->latest('id')->firstOrFail();
        $cancelledAppointmentNumber = $cancelledAppointment->appointment_number;

        $this->actingAs($receptionist)
            ->post(route('appointments.cancel', $cancelledAppointment))
            ->assertRedirect(route('appointments.show', $cancelledAppointment));
        $this->actingAs($receptionist)
            ->post(route('appointments.cancel', $cancelledAppointment))
            ->assertRedirect(route('appointments.show', $cancelledAppointment));

        $this->actingAs($receptionist)
            ->post(route('patients.appointments.store', $patient), [
                'scheduled_at' => '2026-09-13 12:00:00',
            ])
            ->assertRedirect();
        $noShowAppointment = Appointment::query()->latest('id')->firstOrFail();
        $noShowAppointmentNumber = $noShowAppointment->appointment_number;

        $this->actingAs($receptionist)
            ->post(route('appointments.no-show', $noShowAppointment))
            ->assertRedirect(route('appointments.show', $noShowAppointment));
        $this->actingAs($receptionist)
            ->post(route('appointments.no-show', $noShowAppointment))
            ->assertRedirect(route('appointments.show', $noShowAppointment));

        $this->actingAs($receptionist)
            ->post(route('patients.appointments.store', $patient), [
                'scheduled_at' => '2026-09-01 10:00:00',
            ])
            ->assertRedirect();
        $linkedAppointment = Appointment::query()->latest('id')->firstOrFail();
        $linkedAppointmentNumber = $linkedAppointment->appointment_number;

        $this->actingAs($receptionist)
            ->post(route('patients.visits.store', $patient))
            ->assertRedirect();
        $patientVisit = Visit::query()->whereNull('appointment_id')->sole();
        $patientVisitNumber = $patientVisit->visit_number;

        $this->actingAs($receptionist)
            ->post(route('appointments.visit.store', $linkedAppointment))
            ->assertRedirect();
        $appointmentVisit = Visit::query()
            ->where('appointment_id', $linkedAppointment->id)
            ->sole();
        $appointmentVisitNumber = $appointmentVisit->visit_number;

        $this->actingAs($receptionist)
            ->post(route('appointments.visit.store', $linkedAppointment))
            ->assertSessionHasErrors([
                'appointment' => 'A Visit has already been created from this appointment.',
            ]);

        expect(Patient::query()->count())->toBe(1)
            ->and(Appointment::query()->count())->toBe(4)
            ->and(Visit::query()->count())->toBe(2)
            ->and($patient->fresh()->patient_number)->toBe($patientNumber)
            ->and($patient->fresh()->last_name)->toBe('Otieno')
            ->and($rescheduledAppointment->fresh()->appointment_number)->toBe($rescheduledAppointmentNumber)
            ->and($rescheduledAppointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
            ->and($cancelledAppointment->fresh()->appointment_number)->toBe($cancelledAppointmentNumber)
            ->and($cancelledAppointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
            ->and($noShowAppointment->fresh()->appointment_number)->toBe($noShowAppointmentNumber)
            ->and($noShowAppointment->fresh()->status)->toBe(AppointmentStatus::NoShow)
            ->and($linkedAppointment->fresh()->appointment_number)->toBe($linkedAppointmentNumber)
            ->and($linkedAppointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
            ->and($linkedAppointment->fresh()->visit?->is($appointmentVisit))->toBeTrue()
            ->and($patientVisit->fresh()->visit_number)->toBe($patientVisitNumber)
            ->and($appointmentVisit->fresh()->visit_number)->toBe($appointmentVisitNumber)
            ->and($patientVisit->fresh()->status)->toBe(VisitStatus::Created)
            ->and($appointmentVisit->fresh()->status)->toBe(VisitStatus::Created)
            ->and($appointmentVisit->patient->is($patient))->toBeTrue()
            ->and($appointmentVisit->status->handoffLabel())->toBe('Awaiting consultation billing');

        expect(AuditLog::query()->count())->toBe(12)
            ->and(AuditLog::query()->where('action', AuditAction::PatientRegistered)->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditAction::PatientUpdated)->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditAction::AppointmentCreated)->count())->toBe(4)
            ->and(AuditLog::query()->where('action', AuditAction::AppointmentRescheduled)->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditAction::AppointmentCancelled)->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditAction::AppointmentNoShow)->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditAction::VisitCreated)->count())->toBe(2)
            ->and(AuditLog::query()->where('action', AuditAction::AppointmentVisitLinked)->count())->toBe(1);
    });
});

it('keeps Reception queues profiles and detail payloads consistent after an Appointment handoff', function () {
    Carbon::withTestNow('2026-09-01 09:00:00', function (): void {
        $receptionist = phase2Receptionist();
        $patient = Patient::factory()->create([
            'first_name' => 'Amina',
            'middle_name' => null,
            'last_name' => 'Kamau',
        ]);
        $otherPatient = Patient::factory()->create();
        $linkedAppointment = Appointment::factory()->for($patient)->create([
            'scheduled_at' => now()->addHour(),
        ]);
        $awaitingAppointment = Appointment::factory()->for($patient)->create([
            'scheduled_at' => now()->addHours(2),
        ]);
        $unrelatedAppointment = Appointment::factory()->for($otherPatient)->create([
            'scheduled_at' => now()->addHours(3),
        ]);
        $unrelatedAppointment->forceFill(['status' => AppointmentStatus::Cancelled])->save();
        $unrelatedVisit = Visit::factory()->for($otherPatient)->create([
            'occurred_at' => now()->addMinute(),
        ]);

        $this->actingAs($receptionist)
            ->post(route('appointments.visit.store', $linkedAppointment))
            ->assertRedirect();

        $linkedVisit = Visit::query()
            ->where('appointment_id', $linkedAppointment->id)
            ->sole();

        $this->actingAs($receptionist)
            ->get(route('appointments.index', ['awaiting_attendance' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.awaitingAttendance', true)
                ->has('appointments.data', 1)
                ->where('appointments.data.0.id', $awaitingAppointment->id)
                ->where('appointments.data.0.linkedVisit', null)
                ->where('auth.capabilities.viewAppointments', true)
                ->where('auth.capabilities.createVisits', true)
            );

        $this->actingAs($receptionist)
            ->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('patient.id', $patient->id)
                ->where('visitHistory.data', fn ($visits): bool => collect($visits)
                    ->pluck('id')->contains($linkedVisit->id)
                    && ! collect($visits)->pluck('id')->contains($unrelatedVisit->id))
                ->where('upcomingAppointments.data', fn ($appointments): bool => collect($appointments)
                    ->pluck('id')->all() === [$linkedAppointment->id, $awaitingAppointment->id])
                ->missing('patient.billing')
                ->missing('patient.payments')
                ->missing('patient.clearance')
                ->missing('patient.clinical')
                ->missing('patient.procedures')
                ->missing('patient.nursing')
                ->missing('auditLogs')
            );

        $this->actingAs($receptionist)
            ->get(route('appointments.show', $linkedAppointment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointment.status.value', 'scheduled')
                ->where('appointment.patient.id', $patient->id)
                ->where('appointment.linkedVisit.id', $linkedVisit->id)
                ->where('appointment.linkedVisit.nextStep', 'Awaiting consultation billing')
                ->missing('appointment.billing')
                ->missing('appointment.clearance')
                ->missing('appointment.checkIn')
                ->missing('appointment.clinical')
            );

        $this->actingAs($receptionist)
            ->get(route('visits.show', $linkedVisit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.patient.id', $patient->id)
                ->where('visit.appointment.id', $linkedAppointment->id)
                ->where('visit.status.value', 'created')
                ->where('visit.nextStep', 'Awaiting consultation billing')
                ->missing('visit.billing')
                ->missing('visit.payments')
                ->missing('visit.clearance')
                ->missing('visit.checkIn')
                ->missing('visit.clinical')
                ->missing('visit.procedures')
                ->missing('visit.nursing')
            );

        $this->actingAs($receptionist)
            ->get(route('visits.index', ['q' => $linkedVisit->visit_number]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('visits.data', 1)
                ->where('visits.data.0.id', $linkedVisit->id)
                ->where('visits.data.0.status.value', 'created')
                ->where('visits.data.0.nextStep', 'Awaiting consultation billing')
                ->where('visits.data.0.appointment.id', $linkedAppointment->id)
            );
    });
});

it('denies every non-Receptionist role access to the integrated Phase 2 workflow', function (StaffRole $staffRole) {
    $actor = User::factory()->forRole($staffRole)->create();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $visit = Visit::factory()->for($patient)->create();

    $this->actingAs($actor)->get(route('patients.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('patients.show', $patient))->assertForbidden();
    $this->actingAs($actor)->get(route('appointments.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('appointments.show', $appointment))->assertForbidden();
    $this->actingAs($actor)->get(route('visits.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('visits.show', $visit))->assertForbidden();
    $this->actingAs($actor)
        ->post(route('appointments.visit.store', $appointment))
        ->assertForbidden();
})->with([
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('redirects guests away from the integrated Phase 2 workflow', function () {
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $visit = Visit::factory()->for($patient)->create();

    $this->get(route('patients.index'))->assertRedirect(route('login'));
    $this->get(route('patients.show', $patient))->assertRedirect(route('login'));
    $this->get(route('appointments.index'))->assertRedirect(route('login'));
    $this->get(route('appointments.show', $appointment))->assertRedirect(route('login'));
    $this->get(route('visits.index'))->assertRedirect(route('login'));
    $this->get(route('visits.show', $visit))->assertRedirect(route('login'));
    $this->post(route('appointments.visit.store', $appointment))->assertRedirect(route('login'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function phase2PatientPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'sex' => 'female',
        'phone' => '+254700000001',
        'email' => 'amina@example.com',
        'address' => 'Nairobi',
        ...$overrides,
    ];
}

function phase2Receptionist(): User
{
    return User::factory()->forRole(StaffRole::Receptionist)->create();
}
