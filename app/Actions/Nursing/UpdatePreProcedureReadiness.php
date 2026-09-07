<?php

namespace App\Actions\Nursing;

use App\Models\PreProcedureReadiness;
use App\Models\User;
use App\PreProcedureReadinessStatus;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdatePreProcedureReadiness
{
    /** @param array<string, mixed> $attributes */
    public function handle(
        User $actor,
        PreProcedureReadiness $preProcedureReadiness,
        array $attributes,
    ): PreProcedureReadiness {
        Gate::forUser($actor)->authorize('update', $preProcedureReadiness);

        $attributes = $this->normalize($attributes);
        $validated = Validator::make($attributes, [
            'id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'procedure_decision_id' => ['prohibited'],
            'nurse_user_id' => ['prohibited'],
            'readiness_number' => ['prohibited'],
            'status' => ['prohibited'],
            'started_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'consent_verified' => ['required', 'boolean'],
            'patient_identity_verified' => ['required', 'boolean'],
            'procedure_verified' => ['required', 'boolean'],
            'allergies_reviewed' => ['required', 'boolean'],
            'medications_reviewed' => ['required', 'boolean'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $preProcedureReadiness, $validated): PreProcedureReadiness {
            $lockedReadiness = PreProcedureReadiness::query()
                ->lockForUpdate()
                ->find($preProcedureReadiness->getKey());
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Nurse->value);
                })
                ->lockForUpdate()
                ->first();

            if (! $lockedReadiness instanceof PreProcedureReadiness
                || ! $lockedActor instanceof User
                || $lockedReadiness->nurse_user_id !== $lockedActor->getKey()
                || $lockedReadiness->getRawOriginal('status') !== PreProcedureReadinessStatus::InPreparation->value) {
                throw ValidationException::withMessages([
                    'readiness' => 'Only the responsible active Nurse may update an in-progress preparation.',
                ]);
            }

            $lockedReadiness->updateFromNursingWorkflow($lockedActor, $validated);

            return $lockedReadiness->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        if (array_key_exists('observations', $attributes) && is_string($attributes['observations'])) {
            $observations = trim($attributes['observations']);
            $attributes['observations'] = $observations === '' ? null : $observations;
        }

        return $attributes;
    }
}
