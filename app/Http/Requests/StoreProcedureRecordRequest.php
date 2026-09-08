<?php

namespace App\Http\Requests;

use App\Models\ProcedureRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProcedureRecord::class) ?? false;
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
            'findings' => ['prohibited'],
            'diagnosis_impression' => ['prohibited'],
            'specimens_taken' => ['prohibited'],
            'specimen_notes' => ['prohibited'],
            'complications' => ['prohibited'],
            'outcome' => ['prohibited'],
            'procedure_notes' => ['prohibited'],
        ];
    }
}
