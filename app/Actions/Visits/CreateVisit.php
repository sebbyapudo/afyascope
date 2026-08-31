<?php

namespace App\Actions\Visits;

use App\Actions\Audit\RecordAuditLog;
use App\AppointmentStatus;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateVisit
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, Patient $patient, array $attributes = []): Visit
    {
        Gate::forUser($actor)->authorize('create', Visit::class);

        if (! $patient->exists) {
            throw ValidationException::withMessages([
                'patient' => 'The selected patient does not exist.',
            ]);
        }

        $validated = Validator::make($attributes, [
            'id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'visit_number' => ['prohibited'],
            'status' => ['prohibited'],
            'occurred_at' => ['nullable', Rule::date()],
        ])->validate();

        $occurredAt = is_string($validated['occurred_at'] ?? null)
            ? CarbonImmutable::parse($validated['occurred_at'])
            : now()->toImmutable();

        return DB::transaction(fn (): Visit => $this->createVisit(
            actor: $actor,
            patient: $patient,
            occurredAt: $occurredAt,
        ));
    }

    public function fromAppointment(User $actor, Appointment $appointment): Visit
    {
        Gate::forUser($actor)->authorize('view', $appointment);
        Gate::forUser($actor)->authorize('create', Visit::class);

        if (! $appointment->exists) {
            throw ValidationException::withMessages([
                'appointment' => 'The selected appointment does not exist.',
            ]);
        }

        return DB::transaction(function () use ($actor, $appointment): Visit {
            $lockedAppointment = Appointment::query()
                ->with('patient')
                ->lockForUpdate()
                ->find($appointment->getKey());

            if (! $lockedAppointment instanceof Appointment) {
                throw ValidationException::withMessages([
                    'appointment' => 'The selected appointment does not exist.',
                ]);
            }

            if ($lockedAppointment->status !== AppointmentStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'appointment' => 'Only a scheduled appointment can start a Visit.',
                ]);
            }

            if ($lockedAppointment->visit()->exists()) {
                throw ValidationException::withMessages([
                    'appointment' => 'A Visit has already been created from this appointment.',
                ]);
            }

            return $this->createVisit(
                actor: $actor,
                patient: $lockedAppointment->patient,
                occurredAt: now()->toImmutable(),
                appointment: $lockedAppointment,
            );
        });
    }

    private function createVisit(
        User $actor,
        Patient $patient,
        CarbonImmutable $occurredAt,
        ?Appointment $appointment = null,
    ): Visit {
        $visit = new Visit;
        $visit->patient()->associate($patient);
        $visit->occurred_at = $occurredAt;

        if ($appointment instanceof Appointment) {
            $visit->appointment()->associate($appointment);
        }

        $visit->save();

        $this->recordAuditLog->handle(
            actor: $actor,
            action: AuditAction::VisitCreated,
            subject: $visit,
            afterValues: [
                'visit_number' => $visit->visit_number,
                'patient_id' => $patient->getKey(),
                'occurred_at' => $visit->occurred_at->toIso8601String(),
                'status' => $visit->status->value,
            ],
        );

        if ($appointment instanceof Appointment) {
            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::AppointmentVisitLinked,
                subject: $appointment,
                afterValues: [
                    'visit_id' => $visit->getKey(),
                    'visit_number' => $visit->visit_number,
                ],
            );
        }

        return $visit;
    }
}
