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

it('lets a Receptionist confirm a scheduled Appointment handoff', function () {
    $receptionist = appointmentHandoffReceptionist();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
    ]);
    $appointment = Appointment::factory()->for($patient)->create();

    $this->actingAs($receptionist)
        ->get(route('appointments.visit.create', $appointment))
        ->assertInertia(fn (Assert $page) => $page
            ->component('visits/create')
            ->where('patient.id', $patient->id)
            ->where('patient.patientNumber', $patient->patient_number)
            ->where('patient.name', 'Amina Kamau')
            ->where('appointment.id', $appointment->id)
            ->where('appointment.appointmentNumber', $appointment->appointment_number)
            ->where('appointment.isScheduled', true)
            ->where('appointment.linkedVisit', null)
            ->where('auth.capabilities.createVisits', true)
        );
});

it('protects Appointment handoff endpoints from guests and non-Receptionist roles', function (StaffRole $staffRole) {
    $appointment = Appointment::factory()->create();

    $this->get(route('appointments.visit.create', $appointment))
        ->assertRedirect(route('login'));
    $this->post(route('appointments.visit.store', $appointment))
        ->assertRedirect(route('login'));

    $actor = User::factory()->forRole($staffRole)->create();

    $this->actingAs($actor)
        ->get(route('appointments.visit.create', $appointment))
        ->assertForbidden();
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

it('creates one Created Visit for the existing Appointment Patient and audits the handoff', function () {
    Carbon::withTestNow('2026-08-31 10:15:00', function (): void {
        $receptionist = appointmentHandoffReceptionist();
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($patient)->create([
            'scheduled_at' => '2026-08-31 10:00:00',
        ]);

        $response = $this->actingAs($receptionist)
            ->post(route('appointments.visit.store', $appointment));

        $visit = Visit::query()->sole();
        $response
            ->assertRedirect(route('visits.show', $visit))
            ->assertSessionHas(
                'status',
                "Visit {$visit->visit_number} was created from appointment {$appointment->appointment_number}.",
            );

        expect(Patient::query()->count())->toBe(1)
            ->and(Appointment::query()->count())->toBe(1)
            ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
            ->and($appointment->fresh()->visit?->is($visit))->toBeTrue()
            ->and($visit->patient->is($patient))->toBeTrue()
            ->and($visit->appointment?->is($appointment))->toBeTrue()
            ->and($visit->status)->toBe(VisitStatus::Created)
            ->and($visit->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-31 10:15:00');

        $auditLogs = AuditLog::query()->orderBy('id')->get();

        expect($auditLogs)->toHaveCount(2)
            ->and($auditLogs->pluck('action')->all())->toBe([
                AuditAction::VisitCreated,
                AuditAction::AppointmentVisitLinked,
            ])
            ->and($auditLogs->first()?->subject?->is($visit))->toBeTrue()
            ->and($auditLogs->last()?->subject?->is($appointment))->toBeTrue()
            ->and($auditLogs->last()?->after_values)->toBe([
                'visit_id' => $visit->id,
                'visit_number' => $visit->visit_number,
            ]);
    });
});

it('prevents duplicate Visit creation from one Appointment without extra audit events', function () {
    $receptionist = appointmentHandoffReceptionist();
    $appointment = Appointment::factory()->create();

    $this->actingAs($receptionist)
        ->post(route('appointments.visit.store', $appointment))
        ->assertRedirect();
    $this->actingAs($receptionist)
        ->post(route('appointments.visit.store', $appointment))
        ->assertSessionHasErrors([
            'appointment' => 'A Visit has already been created from this appointment.',
        ]);

    expect(Visit::query()->count())->toBe(1)
        ->and($appointment->fresh()->visit)->not->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::VisitCreated)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::AppointmentVisitLinked)->count())->toBe(1);
});

it('rejects terminal Appointment outcomes without creating a Visit or audit event', function (AppointmentStatus $status) {
    $receptionist = appointmentHandoffReceptionist();
    $appointment = Appointment::factory()->create();
    $appointment->forceFill(['status' => $status])->save();

    $this->actingAs($receptionist)
        ->post(route('appointments.visit.store', $appointment))
        ->assertSessionHasErrors([
            'appointment' => 'Only a scheduled appointment can start a Visit.',
        ]);

    expect(Visit::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($appointment->fresh()->status)->toBe($status);
})->with([
    AppointmentStatus::Cancelled,
    AppointmentStatus::NoShow,
]);

it('shows only unlinked scheduled Appointments in the awaiting attendance queue', function () {
    $receptionist = appointmentHandoffReceptionist();
    $patient = Patient::factory()->create();
    $awaitingAttendance = Appointment::factory()->for($patient)->create([
        'scheduled_at' => now()->subHour(),
    ]);
    $linkedAppointment = Appointment::factory()->for($patient)->create([
        'scheduled_at' => now(),
    ]);
    Visit::factory()->for($patient)->create([
        'appointment_id' => $linkedAppointment->id,
    ]);
    $cancelledAppointment = Appointment::factory()->for($patient)->create([
        'scheduled_at' => now()->addHour(),
    ]);
    $cancelledAppointment->forceFill([
        'status' => AppointmentStatus::Cancelled,
    ])->save();

    $this->actingAs($receptionist)
        ->get(route('appointments.index', ['awaiting_attendance' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.awaitingAttendance', true)
            ->has('appointments.data', 1)
            ->where('appointments.data.0.id', $awaitingAttendance->id)
            ->where('appointments.data.0.status.value', 'scheduled')
            ->where('appointments.data.0.linkedVisit', null)
        );
});

it('exposes linked administrative context without billing check-in or clinical data', function () {
    $receptionist = appointmentHandoffReceptionist();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $visit = Visit::factory()->for($patient)->create([
        'appointment_id' => $appointment->id,
    ]);

    $this->actingAs($receptionist)
        ->get(route('appointments.show', $appointment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointment.linkedVisit.id', $visit->id)
            ->where('appointment.linkedVisit.visitNumber', $visit->visit_number)
            ->where('appointment.linkedVisit.status.value', 'created')
            ->where('appointment.linkedVisit.nextStep', 'Awaiting consultation billing')
            ->missing('appointment.billing')
            ->missing('appointment.clearance')
            ->missing('appointment.checkIn')
            ->missing('appointment.clinical')
        );

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.status', ['value' => 'created', 'label' => 'Created'])
            ->where('visit.nextStep', 'Awaiting consultation billing')
            ->where('visit.appointment', [
                'id' => $appointment->id,
                'appointmentNumber' => $appointment->appointment_number,
            ])
            ->missing('visit.billing')
            ->missing('visit.payments')
            ->missing('visit.clearance')
            ->where('visit.checkIn', null)
            ->missing('visit.clinical')
            ->missing('visit.procedures')
            ->missing('visit.nursing')
        );
});

function appointmentHandoffReceptionist(): User
{
    return User::factory()->forRole(StaffRole::Receptionist)->create();
}
