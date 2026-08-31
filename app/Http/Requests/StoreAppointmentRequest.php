<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Appointment::class) ?? false;
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
            'appointment_number' => ['prohibited'],
            'status' => ['prohibited'],
            'scheduled_at' => ['required', Rule::date()->after(now())],
        ];
    }

    /**
     * @return array{scheduled_at: string}
     */
    public function appointmentAttributes(): array
    {
        return [
            'scheduled_at' => (string) $this->validated('scheduled_at'),
        ];
    }
}
