<?php

namespace Database\Factories;

use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\PreProcedureReadinessStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PreProcedureReadiness>
 */
class PreProcedureReadinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consent_verified' => false,
            'patient_identity_verified' => false,
            'procedure_verified' => false,
            'allergies_reviewed' => false,
            'medications_reviewed' => false,
            'observations' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => PreProcedureReadinessStatus::Ready,
            'consent_verified' => true,
            'patient_identity_verified' => true,
            'procedure_verified' => true,
            'allergies_reviewed' => true,
            'medications_reviewed' => true,
            'completed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function createAuthoritativePreparationFixture(
        ProcedureDecision $procedureDecision,
        User $nurse,
        array $attributes = [],
    ): PreProcedureReadiness {
        unset(
            $attributes['visit_id'],
            $attributes['procedure_decision_id'],
            $attributes['nurse_user_id'],
            $attributes['readiness_number'],
            $attributes['started_at'],
        );

        $readiness = $this->makeOne($attributes);
        $readiness->visit_id = $procedureDecision->visit_id;
        $readiness->procedure_decision_id = $procedureDecision->id;
        $readiness->nurse_user_id = $nurse->id;
        $readiness->readiness_number = 'TMP-'.Str::ulid();
        $readiness->status ??= PreProcedureReadinessStatus::InPreparation;
        $readiness->started_at = now();
        $readiness->saveQuietly();
        $readiness->readiness_number = sprintf('PPR-%06d', $readiness->id);
        $readiness->saveQuietly();

        return $readiness->refresh();
    }
}
