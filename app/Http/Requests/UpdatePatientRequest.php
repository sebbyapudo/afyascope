<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\PatientSex;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient
            && ($this->user()?->can('update', $patient) ?? false);
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
            'patient_number' => ['prohibited'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', Rule::date()->todayOrBefore()],
            'sex' => ['nullable', Rule::enum(PatientSex::class)],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{first_name: string, middle_name: string|null, last_name: string, date_of_birth: string|null, sex: string|null, phone: string|null, email: string|null, address: string|null}
     */
    public function patientAttributes(): array
    {
        $validated = $this->validated();

        return [
            'first_name' => (string) $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => (string) $validated['last_name'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->nullableTrimmedString('phone');

        $this->merge([
            'first_name' => $this->trimmedString('first_name'),
            'middle_name' => $this->nullableTrimmedString('middle_name'),
            'last_name' => $this->trimmedString('last_name'),
            'phone' => $phone === null ? null : (preg_replace('/[\s().-]+/', '', $phone) ?: null),
            'email' => $this->nullableTrimmedString('email', lowercase: true),
            'address' => $this->nullableTrimmedString('address'),
        ]);
    }

    private function trimmedString(string $key): string
    {
        return trim((string) $this->input($key));
    }

    private function nullableTrimmedString(string $key, bool $lowercase = false): ?string
    {
        $value = $this->trimmedString($key);

        if ($value === '') {
            return null;
        }

        return $lowercase ? mb_strtolower($value) : $value;
    }
}
