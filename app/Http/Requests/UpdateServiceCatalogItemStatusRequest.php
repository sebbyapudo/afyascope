<?php

namespace App\Http\Requests;

use App\Models\ServiceCatalogItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceCatalogItemStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $serviceCatalogItem = $this->route('serviceCatalogItem');

        return $serviceCatalogItem instanceof ServiceCatalogItem
            && ($this->user()?->can('update', $serviceCatalogItem) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->validated('is_active');
    }
}
