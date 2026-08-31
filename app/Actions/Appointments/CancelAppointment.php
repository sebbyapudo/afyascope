<?php

namespace App\Actions\Appointments;

use App\Actions\Audit\RecordAuditLog;
use App\AppointmentStatus;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelAppointment
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Appointment $appointment): Appointment
    {
        Gate::forUser($actor)->authorize('update', $appointment);

        return DB::transaction(function () use ($actor, $appointment): Appointment {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAppointment->status === AppointmentStatus::Cancelled) {
                return $lockedAppointment;
            }

            if ($lockedAppointment->status !== AppointmentStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'status' => 'Only a scheduled appointment may be cancelled.',
                ]);
            }

            $lockedAppointment->status = AppointmentStatus::Cancelled;
            $lockedAppointment->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::AppointmentCancelled,
                subject: $lockedAppointment,
                beforeValues: ['status' => AppointmentStatus::Scheduled->value],
                afterValues: ['status' => AppointmentStatus::Cancelled->value],
            );

            return $lockedAppointment->refresh();
        });
    }
}
