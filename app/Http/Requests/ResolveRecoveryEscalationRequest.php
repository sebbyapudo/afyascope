<?php

namespace App\Http\Requests;

use App\Actions\Clinical\ResolveRecoveryEscalation;
use App\Models\RecoveryEscalation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ResolveRecoveryEscalationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $recoveryEscalation = $this->route('recoveryEscalation');

        return $actor instanceof User
            && $recoveryEscalation instanceof RecoveryEscalation
            && $actor->can('resolve', $recoveryEscalation);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ResolveRecoveryEscalation::rules();
    }

    /** @return array{resolution: string, resolution_note: string|null} */
    public function resolutionAttributes(): array
    {
        /** @var array{resolution: string, resolution_note: string|null} $attributes */
        $attributes = $this->safe()->only(['resolution', 'resolution_note']);

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $resolutionNote = $this->input('resolution_note');

        $this->merge([
            'resolution_note' => is_string($resolutionNote)
                ? (trim($resolutionNote) ?: null)
                : $resolutionNote,
        ]);
    }
}
