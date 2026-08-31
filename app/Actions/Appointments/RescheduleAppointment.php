<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\RecordAuditLog;
use App\AppointmentStatus;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RescheduleAppointment
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, Appointment $appointment, array $attributes): Appointment
    {
        Gate::forUser($actor)->authorize('update', $appointment);

        $validated = Validator::make($attributes, [
            'id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'appointment_number' => ['prohibited'],
            'status' => ['prohibited'],
            'scheduled_at' => ['required', Rule::date()->after(now())],
        ])->validate();
        $scheduledAt = CarbonImmutable::parse((string) $validated['scheduled_at']);

        return DB::transaction(function () use ($actor, $appointment, $scheduledAt): Appointment {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAppointment->status !== AppointmentStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'scheduled_at' => 'Only a scheduled appointment may be rescheduled.',
                ]);
            }

            if ($lockedAppointment->scheduled_at->equalTo($scheduledAt)) {
                return $lockedAppointment;
            }

            $beforeScheduledAt = $lockedAppointment->scheduled_at->toIso8601String();
            $lockedAppointment->scheduled_at = $scheduledAt;
            $lockedAppointment->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::AppointmentRescheduled,
                subject: $lockedAppointment,
                beforeValues: ['scheduled_at' => $beforeScheduledAt],
                afterValues: ['scheduled_at' => $lockedAppointment->scheduled_at->toIso8601String()],
            );

            return $lockedAppointment->refresh();
        });
    }
}
