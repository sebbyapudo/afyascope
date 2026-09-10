<?php

namespace Database\Factories;

use App\Actions\Nursing\DischargeRecovery;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\RecoveryDischargeAccompanimentStatus;
use App\RecoveryDischargeDisposition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecoveryDischarge>
 */
class RecoveryDischargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'condition_summary' => 'Patient alert and clinically ready for discharge.',
            'accompaniment_status' => RecoveryDischargeAccompanimentStatus::Accompanied->value,
            'disposition' => RecoveryDischargeDisposition::Home->value,
            'nursing_note' => null,
            'general_care_instructions' => 'Rest and follow the documented post-procedure care guidance.',
            'activity_driving_instructions' => 'Avoid driving and strenuous activity for the advised period.',
            'diet_fluids_instructions' => 'Resume fluids and diet as instructed by the clinical team.',
            'medication_instructions' => null,
            'warning_signs_instructions' => 'Seek urgent medical attention for the warning signs discussed.',
            'follow_up_instructions' => null,
            'confirm_discharge' => true,
        ];
    }

    public function createAuthoritativeDischargeFixture(
        RecoveryEpisode $recoveryEpisode,
        ?User $nurse = null,
    ): RecoveryDischarge {
        $nurse ??= $recoveryEpisode->nurse;
        $attributes = $this->makeOne()->getAttributes();

        return app(DischargeRecovery::class)->handle($nurse, $recoveryEpisode, $attributes);
    }
}
