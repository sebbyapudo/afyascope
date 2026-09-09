<?php

namespace App\Http\Requests;

use App\Actions\Nursing\RecordRecoveryObservation;
use App\Models\RecoveryEpisode;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecoveryObservationRequest extends FormRequest
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
            && $actor->can('update', $recoveryEpisode);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return RecordRecoveryObservation::rules();
    }

    /** @return array{general_recovery_status: string, pain_score: int|null, nausea: bool, vomiting: bool, systolic_blood_pressure: int|null, diastolic_blood_pressure: int|null, pulse_rate: int|null, respiratory_rate: int|null, oxygen_saturation: int|null, supplemental_oxygen: bool, nursing_note: string|null} */
    public function observationAttributes(): array
    {
        /** @var array{general_recovery_status: string, pain_score: int|null, nausea: bool, vomiting: bool, systolic_blood_pressure: int|null, diastolic_blood_pressure: int|null, pulse_rate: int|null, respiratory_rate: int|null, oxygen_saturation: int|null, supplemental_oxygen: bool, nursing_note: string|null} $attributes */
        $attributes = $this->safe()->only([
            'general_recovery_status', 'pain_score', 'nausea', 'vomiting',
            'systolic_blood_pressure', 'diastolic_blood_pressure', 'pulse_rate',
            'respiratory_rate', 'oxygen_saturation', 'supplemental_oxygen', 'nursing_note',
        ]);

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'general_recovery_status' => is_string($this->input('general_recovery_status'))
                ? trim($this->input('general_recovery_status'))
                : $this->input('general_recovery_status'),
            'nursing_note' => is_string($this->input('nursing_note'))
                ? (trim($this->input('nursing_note')) ?: null)
                : $this->input('nursing_note'),
        ]);
    }
}
