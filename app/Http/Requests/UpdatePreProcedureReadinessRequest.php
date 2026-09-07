<?php

namespace App\Http\Requests;

use App\Models\PreProcedureReadiness;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePreProcedureReadinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $readiness = $this->route('preProcedureReadiness');

        return $readiness instanceof PreProcedureReadiness
            && ($this->user()?->can('update', $readiness) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'consultation_id' => ['prohibited'],
            'procedure_decision_id' => ['prohibited'],
            'procedure_billing_handoff_id' => ['prohibited'],
            'bill_id' => ['prohibited'],
            'financial_clearance_id' => ['prohibited'],
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
        ];
    }

    /** @return array{consent_verified: bool, patient_identity_verified: bool, procedure_verified: bool, allergies_reviewed: bool, medications_reviewed: bool, observations?: string|null} */
    public function readinessAttributes(): array
    {
        /** @var array{consent_verified: bool, patient_identity_verified: bool, procedure_verified: bool, allergies_reviewed: bool, medications_reviewed: bool, observations?: string|null} $validated */
        $validated = $this->safe()->only([
            'consent_verified',
            'patient_identity_verified',
            'procedure_verified',
            'allergies_reviewed',
            'medications_reviewed',
            'observations',
        ]);

        return $validated;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('observations') || ! is_string($this->input('observations'))) {
            return;
        }

        $observations = trim((string) $this->input('observations'));
        $this->merge(['observations' => $observations === '' ? null : $observations]);
    }
}
