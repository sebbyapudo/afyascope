<?php

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CreateAppointment;
use App\Actions\Appointments\MarkAppointmentNoShow;
use App\Actions\Appointments\RescheduleAppointment;
use App\Actions\Audit\RecordAuditLog;
use App\AppointmentStatus;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('creates a scheduled Appointment for an existing Patient and audits it', function () {
    Carbon::withTestNow('2026-08-31 09:00:00', function (): void {
        $actor = appointmentActionReceptionist();
        $patient = Patient::factory()->create();

        $appointment = app(CreateAppointment::class)->handle($actor, $patient, [
            'scheduled_at' => '2026-09-02 10:30:00',
        ]);

        expect($appointment->patient->is($patient))->toBeTrue()
            ->and($appointment->appointment_number)->toMatch('/^APT-\d{6,}$/')
            ->and($appointment->scheduled_at->format('Y-m-d H:i:s'))->toBe('2026-09-02 10:30:00')
            ->and($appointment->status)->toBe(AppointmentStatus::Scheduled);

        $auditLog = AuditLog::query()->sole();
        expect($auditLog->action)->toBe(AuditAction::AppointmentCreated)
            ->and($auditLog->actor->is($actor))->toBeTrue()
            ->and($auditLog->subject->is($appointment))->toBeTrue()
            ->and($auditLog->before_values)->toBeNull()
            ->and($auditLog->after_values)->toHaveKey('appointment_number', $appointment->appointment_number)
            ->and($auditLog->after_values)->toHaveKey('patient_id', $patient->id)
            ->and($auditLog->after_values)->toHaveKey('status', 'scheduled')
            ->and($auditLog->after_values)->toHaveCount(4);
    });
});

it('reschedules a scheduled Appointment and audits only the changed time', function () {
    Carbon::withTestNow('2026-08-31 09:00:00', function (): void {
        $actor = appointmentActionReceptionist();
        $appointment = Appointment::factory()->create([
            'scheduled_at' => '2026-09-02 10:30:00',
        ]);

        $updatedAppointment = app(RescheduleAppointment::class)->handle($actor, $appointment, [
            'scheduled_at' => '2026-09-03 14:15:00',
        ]);

        expect($updatedAppointment->scheduled_at->format('Y-m-d H:i:s'))->toBe('2026-09-03 14:15:00')
            ->and($updatedAppointment->status)->toBe(AppointmentStatus::Scheduled);

        $auditLog = AuditLog::query()->sole();
        expect($auditLog->action)->toBe(AuditAction::AppointmentRescheduled)
            ->and($auditLog->before_values)->toHaveKey('scheduled_at', '2026-09-02T10:30:00+00:00')
            ->and($auditLog->after_values)->toHaveKey('scheduled_at', '2026-09-03T14:15:00+00:00')
            ->and($auditLog->before_values)->toHaveCount(1)
            ->and($auditLog->after_values)->toHaveCount(1);
    });
});

it('does not audit an unchanged Appointment schedule', function () {
    Carbon::withTestNow('2026-08-31 09:00:00', function (): void {
        $actor = appointmentActionReceptionist();
        $appointment = Appointment::factory()->create([
            'scheduled_at' => '2026-09-02 10:30:00',
        ]);

        app(RescheduleAppointment::class)->handle($actor, $appointment, [
            'scheduled_at' => '2026-09-02 10:30:00',
        ]);

        expect(AuditLog::query()->count())->toBe(0);
    });
});

it('cancels a scheduled Appointment without deleting it and avoids duplicate audit events', function () {
    $actor = appointmentActionReceptionist();
    $appointment = Appointment::factory()->create();

    app(CancelAppointment::class)->handle($actor, $appointment);
    app(CancelAppointment::class)->handle($actor, $appointment);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(Appointment::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::AppointmentCancelled);
});

it('marks a scheduled Appointment as a no-show without deleting it', function () {
    $actor = appointmentActionReceptionist();
    $appointment = Appointment::factory()->create();

    app(MarkAppointmentNoShow::class)->handle($actor, $appointment);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and(Appointment::query()->count())->toBe(1)
        ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::AppointmentNoShow);
});

it('prevents changing a terminal Appointment into another state', function () {
    Carbon::withTestNow('2026-08-31 09:00:00', function (): void {
        $actor = appointmentActionReceptionist();
        $appointment = Appointment::factory()->create();
        app(CancelAppointment::class)->handle($actor, $appointment);

        expect(fn () => app(RescheduleAppointment::class)->handle($actor, $appointment, [
            'scheduled_at' => '2026-09-04 12:00:00',
        ]))->toThrow(ValidationException::class)
            ->and(fn () => app(MarkAppointmentNoShow::class)->handle($actor, $appointment))
            ->toThrow(ValidationException::class)
            ->and(AuditLog::query()->count())->toBe(1);
    });
});

it('rejects controlled fields and past schedule values at the action boundary', function (array $attributes) {
    Carbon::withTestNow('2026-08-31 09:00:00', function () use ($attributes): void {
        $actor = appointmentActionReceptionist();
        $patient = Patient::factory()->create();

        expect(fn () => app(CreateAppointment::class)->handle(
            $actor,
            $patient,
            ['scheduled_at' => '2026-09-02 10:30:00', ...$attributes],
        ))->toThrow(ValidationException::class);

        expect(Appointment::query()->count())->toBe(0)
            ->and(AuditLog::query()->count())->toBe(0);
    });
})->with([
    'primary key' => [['id' => 999999]],
    'Patient foreign key' => [['patient_id' => 999999]],
    'Appointment reference' => [['appointment_number' => 'APT-999999']],
    'status' => [['status' => 'cancelled']],
    'past schedule' => [['scheduled_at' => '2026-08-30 10:30:00']],
]);

it('enforces Appointment authorization at every action boundary', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();

    expect(fn () => app(CreateAppointment::class)->handle($administrator, $patient, [
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RescheduleAppointment::class)->handle($administrator, $appointment, [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CancelAppointment::class)->handle($administrator, $appointment))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(MarkAppointmentNoShow::class)->handle($administrator, $appointment))
        ->toThrow(AuthorizationException::class);
});

it('rolls back cancellation when audit recording fails', function () {
    $actor = appointmentActionReceptionist();
    $appointment = Appointment::factory()->create();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Audit write failed.'));

    expect(fn () => (new CancelAppointment($recordAuditLog))->handle(
        $actor,
        $appointment,
    ))->toThrow(RuntimeException::class, 'Audit write failed.');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and(AuditLog::query()->count())->toBe(0);
});

function appointmentActionReceptionist(): User
{
    return User::factory()->forRole(StaffRole::Receptionist)->create();
}
