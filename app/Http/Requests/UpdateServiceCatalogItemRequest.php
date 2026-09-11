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
            'unit_price' => ['required', 'decimal:0,2', 'min:0.01', 'max:9999999999999999.99'],
        ];
    }

    /** @return array{name: string, category: string, unit_price_minor: int} */
    public function serviceAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'category' => (string) $validated['category'],
            'unit_price_minor' => $this->priceMinor((string) $validated['unit_price']),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'category' => $this->string('category')->trim()->lower()->toString(),
            'unit_price' => $this->string('unit_price')->trim()->toString(),
        ]);
    }

    private function priceMinor(string $price): int
    {
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
