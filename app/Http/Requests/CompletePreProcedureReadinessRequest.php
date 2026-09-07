<?php

namespace App\Http\Requests;

use App\Models\PreProcedureReadiness;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompletePreProcedureReadinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $readiness = $this->route('preProcedureReadiness');

        return $readiness instanceof PreProcedureReadiness
            && ($this->user()?->can('complete', $readiness) ?? false);
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
            'consent_verified' => ['prohibited'],
            'patient_identity_verified' => ['prohibited'],
            'procedure_verified' => ['prohibited'],
            'allergies_reviewed' => ['prohibited'],
            'medications_reviewed' => ['prohibited'],
            'observations' => ['prohibited'],
        ];
    }
}
