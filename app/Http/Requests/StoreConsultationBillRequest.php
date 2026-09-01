<?php

namespace App\Http\Requests;

use App\BillType;
use App\Models\Bill;
use App\Models\ServiceCatalogItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsultationBillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Bill::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_catalog_item_id' => [
                'required',
                'integer',
                Rule::exists('service_catalog_items', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('category', BillType::Consultation->value)
                        ->where('is_active', true),
                ),
            ],
            'id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'bill_number' => ['prohibited'],
            'type' => ['prohibited'],
            'status' => ['prohibited'],
            'amount' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'description' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_catalog_item_id.required' => 'Select a consultation service.',
            'service_catalog_item_id.exists' => 'Select an active consultation service.',
        ];
    }

    public function serviceCatalogItem(): ServiceCatalogItem
    {
        return ServiceCatalogItem::query()->findOrFail(
            $this->integer('service_catalog_item_id'),
        );
    }
}
