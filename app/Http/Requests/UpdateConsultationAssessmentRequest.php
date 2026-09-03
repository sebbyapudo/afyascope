<?php

namespace App\Http\Requests;

use App\AsaClassification;
use App\Models\Consultation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsultationAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $consultation = $this->route('consultation');

        return $consultation instanceof Consultation
            && ($this->user()?->can('update', $consultation) ?? false);
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
            'doctor_user_id' => ['prohibited'],
            'consultation_number' => ['prohibited'],
            'status' => ['prohibited'],
            'started_at' => ['prohibited'],
            'finalized_at' => ['prohibited'],
            'check_in_id' => ['prohibited'],
            'bill_id' => ['prohibited'],
            'payment_id' => ['prohibited'],
            'receipt_id' => ['prohibited'],
            'financial_clearance_id' => ['prohibited'],
            'procedure_billing_handoff_id' => ['prohibited'],
            'presenting_complaint' => ['nullable', 'string', 'max:5000'],
            'relevant_history' => ['nullable', 'string', 'max:5000'],
            'current_medications' => ['nullable', 'string', 'max:5000'],
            'allergies' => ['nullable', 'string', 'max:5000'],
            'examination_findings' => ['nullable', 'string', 'max:5000'],
            'asa_classification' => ['nullable', Rule::enum(AsaClassification::class)],
            'assessment_impression' => ['nullable', 'string', 'max:5000'],
            'plan_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{presenting_complaint?: string|null, relevant_history?: string|null, current_medications?: string|null, allergies?: string|null, examination_findings?: string|null, asa_classification?: string|null, assessment_impression?: string|null, plan_notes?: string|null}
     */
    public function assessmentAttributes(): array
    {
        /** @var array{presenting_complaint?: string|null, relevant_history?: string|null, current_medications?: string|null, allergies?: string|null, examination_findings?: string|null, asa_classification?: string|null, assessment_impression?: string|null, plan_notes?: string|null} $validated */
        $validated = $this->safe()->only([
            'presenting_complaint',
            'relevant_history',
            'current_medications',
            'allergies',
            'examination_findings',
            'asa_classification',
            'assessment_impression',
            'plan_notes',
        ]);

        return $validated;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'presenting_complaint',
            'relevant_history',
            'current_medications',
            'allergies',
            'examination_findings',
            'asa_classification',
            'assessment_impression',
            'plan_notes',
        ] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            if (! is_string($value)) {
                $normalized[$field] = $value;

                continue;
            }

            $value = trim($value);
            $normalized[$field] = $value === '' ? null : $value;
        }

        $this->merge($normalized);
    }
}
