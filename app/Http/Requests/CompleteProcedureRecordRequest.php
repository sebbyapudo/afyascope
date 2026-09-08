<?php

namespace App\Http\Requests;

use App\Models\ProcedureRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteProcedureRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $procedureRecord = $this->route('procedureRecord');

        return $procedureRecord instanceof ProcedureRecord
            && ($this->user()?->can('complete', $procedureRecord) ?? false);
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
            'procedure_decision_id' => ['prohibited'],
            'pre_procedure_readiness_id' => ['prohibited'],
            'service_catalog_item_id' => ['prohibited'],
            'doctor_user_id' => ['prohibited'],
            'procedure_number' => ['prohibited'],
            'status' => ['prohibited'],
            'lock_version' => ['prohibited'],
            'started_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'findings' => ['prohibited'],
            'diagnosis_impression' => ['prohibited'],
            'specimens_taken' => ['prohibited'],
            'specimen_notes' => ['prohibited'],
            'complications' => ['prohibited'],
            'outcome' => ['prohibited'],
            'procedure_notes' => ['prohibited'],
            'expected_lock_version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function expectedLockVersion(): int
    {
        return $this->integer('expected_lock_version');
    }
}
