<?php

namespace App\Http\Requests;

use App\Models\Bill;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureBillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Bill::class) ?? false;
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
            'patient_id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'procedure_billing_handoff_id' => ['prohibited'],
            'procedure_decision_id' => ['prohibited'],
            'service_catalog_item_id' => ['prohibited'],
            'bill_number' => ['prohibited'],
            'type' => ['prohibited'],
            'status' => ['prohibited'],
            'amount' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'description' => ['prohibited'],
            'decided_by_user_id' => ['prohibited'],
            'doctor_user_id' => ['prohibited'],
        ];
    }
}
