<?php

namespace App\Http\Requests;

use App\Models\Consultation;
use App\ProcedureDecisionOutcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcedureDecisionRequest extends FormRequest
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
        $isProcedureRequired = $this->input('outcome') === ProcedureDecisionOutcome::ProcedureRequired->value;
        $isNoProcedure = $this->input('outcome') === ProcedureDecisionOutcome::NoProcedure->value;

        return [
            'id' => ['prohibited'],
            'consultation_id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'doctor_id' => ['prohibited'],
            'doctor_user_id' => ['prohibited'],
            'decision_number' => ['prohibited'],
            'decided_at' => ['prohibited'],
            'procedure_billing_handoff_id' => ['prohibited'],
            'handoff_number' => ['prohibited'],
            'bill_id' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'unit_price_minor' => ['prohibited'],
            'price' => ['prohibited'],
            'outcome' => ['required', Rule::enum(ProcedureDecisionOutcome::class)],
            'service_catalog_item_id' => [
                Rule::requiredIf($isProcedureRequired),
                Rule::prohibitedIf($isNoProcedure),
                'nullable',
                'integer',
                'exists:service_catalog_items,id',
            ],
            'clinical_rationale' => ['nullable', 'string', 'max:2000'],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array{outcome: string, service_catalog_item_id?: int|null, clinical_rationale?: string|null, confirmed: mixed}
     */
    public function decisionAttributes(): array
    {
        /** @var array{outcome: string, service_catalog_item_id?: int|null, clinical_rationale?: string|null, confirmed: mixed} $validated */
        $validated = $this->safe()->only([
            'outcome',
            'service_catalog_item_id',
            'clinical_rationale',
            'confirmed',
        ]);

        return $validated;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('clinical_rationale') || ! is_string($this->input('clinical_rationale'))) {
            return;
        }

        $clinicalRationale = trim((string) $this->input('clinical_rationale'));
        $this->merge([
            'clinical_rationale' => $clinicalRationale === '' ? null : $clinicalRationale,
        ]);
    }
}
