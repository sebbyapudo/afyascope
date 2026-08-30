<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FindPatientDuplicatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('create', Patient::class) === true
            && $user->can('viewAny', Patient::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * @return array{first_name: string|null, last_name: string|null, date_of_birth: string|null, phone: string|null, email: string|null}
     */
    public function matchingAttributes(): array
    {
        $validated = $this->validated();

        return [
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->nullableTrimmedString('phone');

        $this->merge([
            'first_name' => $this->nullableTrimmedString('first_name'),
            'last_name' => $this->nullableTrimmedString('last_name'),
            'phone' => $phone === null ? null : (preg_replace('/[\s().-]+/', '', $phone) ?: null),
            'email' => $this->nullableTrimmedString('email', lowercase: true),
        ]);
    }

    private function nullableTrimmedString(string $key, bool $lowercase = false): ?string
    {
        $value = trim((string) $this->input($key));

        if ($value === '') {
            return null;
        }

        return $lowercase ? mb_strtolower($value) : $value;
    }
}
