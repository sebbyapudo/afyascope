<?php

namespace App\Http\Requests;

use App\Models\ServiceCatalogItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceCatalogItemPriceRequest extends FormRequest
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
            'unit_price' => ['required', 'decimal:0,2', 'min:0.01', 'max:9999999999999999.99'],
            'current_unit_price_minor' => ['required', 'integer', 'min:1'],
        ];
    }

    public function unitPriceMinor(): int
    {
        return $this->priceMinor((string) $this->validated('unit_price'));
    }

    public function expectedUnitPriceMinor(): int
    {
        return (int) $this->validated('current_unit_price_minor');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_price' => $this->string('unit_price')->trim()->toString(),
        ]);
    }

    private function priceMinor(string $price): int
    {
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
