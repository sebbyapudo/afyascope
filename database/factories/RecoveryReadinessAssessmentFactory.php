<?php

namespace Database\Factories;

use App\Models\RecoveryEpisode;
use App\Models\RecoveryReadinessAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecoveryReadinessAssessment>
 */
class RecoveryReadinessAssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'criteria_met' => false,
            'clinical_concern_requires_escalation' => false,
            'assessment_note' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'criteria_met' => true,
            'clinical_concern_requires_escalation' => false,
        ]);
    }

    public function escalated(): static
    {
        return $this->state(fn (): array => [
            'criteria_met' => false,
            'clinical_concern_requires_escalation' => true,
        ]);
    }

    public function createAuthoritativeAssessmentFixture(
        RecoveryEpisode $recoveryEpisode,
        ?User $nurse = null,
    ): RecoveryReadinessAssessment {
        $nurse ??= $recoveryEpisode->nurse;
        $attributes = $this->makeOne()->getAttributes();

        return RecoveryReadinessAssessment::recordFromNursingWorkflow(
            $recoveryEpisode,
            $nurse,
            [
                'criteria_met' => (bool) $attributes['criteria_met'],
                'clinical_concern_requires_escalation' => (bool) $attributes['clinical_concern_requires_escalation'],
                'assessment_note' => $attributes['assessment_note'],
            ],
            $recoveryEpisode->readinessAssessment()->first(),
        )->refresh();
    }
}
