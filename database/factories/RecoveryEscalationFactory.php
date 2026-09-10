<?php

namespace Database\Factories;

use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\User;
use App\RecoveryEscalationResolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecoveryEscalation>
 */
class RecoveryEscalationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reason' => 'Clinical review is required before recovery can progress.',
        ];
    }

    public function createAuthoritativeEscalationFixture(
        RecoveryEpisode $recoveryEpisode,
        ?User $nurse = null,
    ): RecoveryEscalation {
        $nurse ??= $recoveryEpisode->nurse;
        $attributes = $this->makeOne()->getAttributes();

        return RecoveryEscalation::escalateFromNursingWorkflow(
            $recoveryEpisode,
            $nurse,
            $attributes['reason'],
        )->refresh();
    }

    public function createResolvedEscalationFixture(
        RecoveryEpisode $recoveryEpisode,
        User $doctor,
        RecoveryEscalationResolution $resolution = RecoveryEscalationResolution::ContinueMonitoring,
    ): RecoveryEscalation {
        $escalation = $this->createAuthoritativeEscalationFixture($recoveryEpisode);
        $escalation->resolveFromClinicalWorkflow($doctor, $resolution, null);

        return $escalation->refresh();
    }
}
