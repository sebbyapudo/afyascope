<?php

namespace App\Http\Requests;

use App\Models\VisitCheckIn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitCheckInRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', VisitCheckIn::class) ?? false;
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
            'bill_id' => ['prohibited'],
            'payment_id' => ['prohibited'],
            'receipt_id' => ['prohibited'],
            'financial_clearance_id' => ['prohibited'],
            'check_in_number' => ['prohibited'],
            'checked_in_by_user_id' => ['prohibited'],
            'checked_in_at' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
