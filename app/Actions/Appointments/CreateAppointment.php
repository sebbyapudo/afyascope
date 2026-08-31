<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateAppointment
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, Patient $patient, array $attributes): Appointment
    {
        Gate::forUser($actor)->authorize('create', Appointment::class);

        if (! $patient->exists) {
            throw ValidationException::withMessages([
                'patient' => 'The selected patient does not exist.',
            ]);
        }

        $validated = Validator::make($attributes, [
            'id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'appointment_number' => ['prohibited'],
            'status' => ['prohibited'],
            'scheduled_at' => ['required', Rule::date()->after(now())],
        ])->validate();

        return DB::transaction(function () use ($actor, $patient, $validated): Appointment {
            $appointment = new Appointment;
            $appointment->patient()->associate($patient);
            $appointment->scheduled_at = $validated['scheduled_at'];
            $appointment->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::AppointmentCreated,
                subject: $appointment,
                afterValues: [
                    'appointment_number' => $appointment->appointment_number,
                    'patient_id' => $patient->getKey(),
                    'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
                    'status' => $appointment->status->value,
                ],
            );

            return $appointment;
        });
    }
}
