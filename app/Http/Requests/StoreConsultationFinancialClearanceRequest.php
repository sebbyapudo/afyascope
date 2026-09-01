<?php

namespace App\Http\Requests;

use App\Models\FinancialClearance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationFinancialClearanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialClearance::class) ?? false;
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
            'bill_id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'clearance_number' => ['prohibited'],
            'granted_by_user_id' => ['prohibited'],
            'granted_at' => ['prohibited'],
            'status' => ['prohibited'],
            'is_cleared' => ['prohibited'],
            'checked_in_at' => ['prohibited'],
        ];
    }
}
