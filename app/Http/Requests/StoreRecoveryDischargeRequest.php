<?php

namespace App\Http\Requests;

use App\Actions\Nursing\DischargeRecovery;
use App\Models\RecoveryEpisode;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecoveryDischargeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $recoveryEpisode = $this->route('recoveryEpisode');

        return $actor instanceof User
            && $recoveryEpisode instanceof RecoveryEpisode
            && $actor->can('discharge', $recoveryEpisode);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return DischargeRecovery::rules();
    }

    /** @return array<string, mixed> */
    public function dischargeAttributes(): array
    {
        return $this->safe()->only([
            'condition_summary',
            'accompaniment_status',
            'disposition',
            'nursing_note',
            'general_care_instructions',
            'activity_driving_instructions',
            'diet_fluids_instructions',
            'medication_instructions',
            'warning_signs_instructions',
            'follow_up_instructions',
            'confirm_discharge',
        ]);
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'condition_summary',
            'nursing_note',
            'general_care_instructions',
            'activity_driving_instructions',
            'diet_fluids_instructions',
            'medication_instructions',
            'warning_signs_instructions',
            'follow_up_instructions',
        ] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value) ?: null]);
            }
        }
    }
}
