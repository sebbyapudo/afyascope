<?php

namespace App\Http\Requests;

use App\Models\RecoveryEpisode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecoveryEpisodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', RecoveryEpisode::class) ?? false;
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
            'payment_id' => ['prohibited'],
            'receipt_id' => ['prohibited'],
            'financial_clearance_id' => ['prohibited'],
            'pre_procedure_readiness_id' => ['prohibited'],
            'procedure_record_id' => ['prohibited'],
            'nurse_user_id' => ['prohibited'],
            'recovery_number' => ['prohibited'],
            'status' => ['prohibited'],
            'started_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'observations' => ['prohibited'],
            'recovery_notes' => ['prohibited'],
            'discharge_status' => ['prohibited'],
        ];
    }
}
