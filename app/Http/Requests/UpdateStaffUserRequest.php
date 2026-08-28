<?php

namespace App\Http\Requests;

use App\Models\User;
use App\StaffRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $staffUser = $this->route('staffUser');

        return $staffUser instanceof User
            && ($this->user()?->can('update', $staffUser) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $staffUser = $this->route('staffUser');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($staffUser instanceof User ? $staffUser : null),
            ],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, email: string, role: string, is_active: bool}
     */
    public function staffAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'role' => (string) $validated['role'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->lower()->toString(),
        ]);
    }
}
