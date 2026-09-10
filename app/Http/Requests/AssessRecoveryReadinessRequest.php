<?php

namespace App\Http\Requests;

use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Models\RecoveryEpisode;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AssessRecoveryReadinessRequest extends FormRequest
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
            && $actor->can('assessReadiness', $recoveryEpisode);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return AssessRecoveryReadiness::rules();
    }

    /** @return array{criteria_met: bool, clinical_concern_requires_escalation: bool, assessment_note: string|null, escalation_reason: string|null} */
    public function assessmentAttributes(): array
    {
        /** @var array{criteria_met: bool, clinical_concern_requires_escalation: bool, assessment_note: string|null, escalation_reason: string|null} $attributes */
        $attributes = $this->safe()->only([
            'criteria_met',
            'clinical_concern_requires_escalation',
            'assessment_note',
            'escalation_reason',
        ]);

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'assessment_note' => $this->trimmedNullableString('assessment_note'),
            'escalation_reason' => $this->trimmedNullableString('escalation_reason'),
        ]);
    }

    private function trimmedNullableString(string $field): mixed
    {
        $value = $this->input($field);

        return is_string($value) ? (trim($value) ?: null) : $value;
    }
}
