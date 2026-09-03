<?php

namespace App\Http\Requests;

use App\Models\Consultation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Consultation::class) ?? false;
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
        ];
    }
}
