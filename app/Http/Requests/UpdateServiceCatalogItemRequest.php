<?php

namespace App\Http\Requests;

use App\BillType;
use App\Models\ServiceCatalogItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCatalogItemRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique(ServiceCatalogItem::class, 'name')
                    ->where(fn ($query) => $query->where('category', $this->input('category')))
                    ->ignore($this->route('serviceCatalogItem')),
            ],
            'category' => ['required', Rule::enum(BillType::class)],
        ];
    }

    /** @return array{name: string, category: string} */
    public function serviceAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'category' => (string) $validated['category'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'category' => $this->string('category')->trim()->lower()->toString(),
        ]);
    }
}
