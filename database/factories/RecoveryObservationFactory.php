<?php

namespace Database\Factories;

use App\Models\RecoveryEpisode;
use App\Models\RecoveryObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecoveryObservation>
 */
class RecoveryObservationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'general_recovery_status' => 'Awake and recovering comfortably',
            'pain_score' => fake()->numberBetween(0, 5),
            'nausea' => false,
            'vomiting' => false,
            'systolic_blood_pressure' => 120,
            'diastolic_blood_pressure' => 80,
            'pulse_rate' => 72,
            'respiratory_rate' => 16,
            'oxygen_saturation' => 98,
            'supplemental_oxygen' => false,
            'nursing_note' => null,
        ];
    }

    public function createAuthoritativeObservationFixture(RecoveryEpisode $recoveryEpisode, ?User $nurse = null): RecoveryObservation
    {
        $nurse ??= $recoveryEpisode->nurse;
        $attributes = $this->makeOne()->getAttributes();

        return RecoveryObservation::recordFromNursingWorkflow($recoveryEpisode, $nurse, [
            'general_recovery_status' => $attributes['general_recovery_status'],
            'pain_score' => $attributes['pain_score'],
            'nausea' => (bool) $attributes['nausea'],
            'vomiting' => (bool) $attributes['vomiting'],
            'systolic_blood_pressure' => $attributes['systolic_blood_pressure'],
            'diastolic_blood_pressure' => $attributes['diastolic_blood_pressure'],
            'pulse_rate' => $attributes['pulse_rate'],
            'respiratory_rate' => $attributes['respiratory_rate'],
            'oxygen_saturation' => $attributes['oxygen_saturation'],
            'supplemental_oxygen' => (bool) $attributes['supplemental_oxygen'],
            'nursing_note' => $attributes['nursing_note'],
        ])->refresh();
    }
}
