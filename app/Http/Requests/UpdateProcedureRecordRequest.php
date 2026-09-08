<?php

namespace App\Http\Requests;

use App\Models\ProcedureRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedureRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $procedureRecord = $this->route('procedureRecord');

        return $procedureRecord instanceof ProcedureRecord
            && ($this->user()?->can('update', $procedureRecord) ?? false);
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
            'pre_procedure_readiness_id' => ['prohibited'],
            'service_catalog_item_id' => ['prohibited'],
            'doctor_user_id' => ['prohibited'],
            'procedure_number' => ['prohibited'],
            'status' => ['prohibited'],
            'lock_version' => ['prohibited'],
            'started_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'findings' => ['present', 'nullable', 'string', 'max:5000'],
            'diagnosis_impression' => ['present', 'nullable', 'string', 'max:5000'],
            'specimens_taken' => ['required', 'boolean'],
            'specimen_notes' => ['present', 'nullable', 'string', 'max:5000'],
            'complications' => ['present', 'nullable', 'string', 'max:5000'],
            'outcome' => ['present', 'nullable', 'string', 'max:5000'],
            'procedure_notes' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public function procedureAttributes(): array
    {
        /** @var array<string, bool|int|string|null> $validated */
        $validated = $this->safe()->only([
            'expected_lock_version',
            'findings',
            'diagnosis_impression',
            'specimens_taken',
            'specimen_notes',
            'complications',
            'outcome',
            'procedure_notes',
        ]);

        return $validated;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'findings',
            'diagnosis_impression',
            'specimen_notes',
            'complications',
            'outcome',
            'procedure_notes',
        ] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $this->merge([$field => $value === '' ? null : $value]);
        }

        if (! $this->boolean('specimens_taken')) {
            $this->merge(['specimen_notes' => null]);
        }
    }
}
