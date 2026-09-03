<?php

namespace App\Models;

use App\BillType;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceCatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property BillType $category
 * @property int $unit_price_minor
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, BillItem> $billItems
 * @property-read Collection<int, ProcedureBillingHandoff> $procedureBillingHandoffs
 * @property-read Collection<int, ProcedureDecision> $procedureDecisions
 */
#[Fillable(['name', 'category', 'unit_price_minor', 'is_active'])]
class ServiceCatalogItem extends Model
{
    /** @use HasFactory<ServiceCatalogItemFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return HasMany<BillItem, $this>
     */
    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /**
     * @return HasMany<ProcedureBillingHandoff, $this>
     */
    public function procedureBillingHandoffs(): HasMany
    {
        return $this->hasMany(ProcedureBillingHandoff::class);
    }

    /** @return HasMany<ProcedureDecision, $this> */
    public function procedureDecisions(): HasMany
    {
        return $this->hasMany(ProcedureDecision::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => BillType::class,
            'is_active' => 'boolean',
            'unit_price_minor' => 'integer',
        ];
    }
}
