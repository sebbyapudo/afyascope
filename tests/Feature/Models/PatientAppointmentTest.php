<?php

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('defines the Patient and Appointment relationship with minimal statuses', function () {
    $patient = Patient::factory()->create();
    $appointments = Appointment::factory()->count(2)->for($patient)->create();

    expect($patient->appointments)->toHaveCount(2)
        ->and($patient->appointments->modelKeys())->toBe($appointments->modelKeys())
        ->and($appointments->every(
            fn (Appointment $appointment): bool => $appointment->patient->is($patient),
        ))->toBeTrue()
        ->and(array_column(AppointmentStatus::cases(), 'value'))->toBe([
            'scheduled',
            'cancelled',
            'no_show',
        ]);
});

it('generates immutable sequential Appointment references at the model boundary', function () {
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create([
        'id' => 42,
        'appointment_number' => 'APT-SUPPLIED',
    ]);

    expect($appointment->appointment_number)->toBe('APT-000042')
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled);

    $appointment->appointment_number = 'APT-CHANGED';

    expect(fn () => $appointment->save())->toThrow(LogicException::class)
        ->and($appointment->fresh()->appointment_number)->toBe('APT-000042');
});

it('produces unique references during burst-style Appointment creation', function () {
    $patient = Patient::factory()->create();
    $appointments = Appointment::factory()->for($patient)->count(25)->create();

    expect($appointments->pluck('appointment_number')->unique())->toHaveCount(25);

    $appointments->each(function (Appointment $appointment): void {
        expect($appointment->appointment_number)->toBe(
            'APT-'.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT),
        );
    });
});

it('prevents reassigning an Appointment to another Patient', function () {
    $appointment = Appointment::factory()->create();
    $otherPatient = Patient::factory()->create();
    $originalPatientId = $appointment->patient_id;
    $appointment->patient()->associate($otherPatient);

    expect(fn () => $appointment->save())->toThrow(LogicException::class)
        ->and($appointment->fresh()->patient_id)->toBe($originalPatientId);
});

it('enforces Appointment reference and Patient constraints at the database boundary', function () {
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();

    expect(fn () => DB::table('appointments')->insert([
        'patient_id' => $patient->id,
        'appointment_number' => $appointment->appointment_number,
        'scheduled_at' => now()->addDay(),
        'status' => AppointmentStatus::Scheduled->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('appointments')->insert([
        'patient_id' => 999999,
        'appointment_number' => 'APT-999999',
        'scheduled_at' => now()->addDay(),
        'status' => AppointmentStatus::Scheduled->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => $patient->delete())->toThrow(QueryException::class)
        ->and($patient->fresh())->not->toBeNull()
        ->and($appointment->fresh())->not->toBeNull();
});
