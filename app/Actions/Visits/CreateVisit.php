<?php

namespace App\Actions\Visits;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
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
            'visit_number' => ['prohibited'],
            'status' => ['prohibited'],
            'occurred_at' => ['nullable', Rule::date()],
        ])->validate();

        return DB::transaction(function () use ($actor, $patient, $validated): Visit {
            $visit = new Visit;
            $visit->patient()->associate($patient);
            $visit->occurred_at = $validated['occurred_at'] ?? now();
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

            return $visit;
        });
    }
}
