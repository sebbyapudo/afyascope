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
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, BillItem> $billItems
 */
#[Fillable(['name', 'category', 'unit_price_minor'])]
class ServiceCatalogItem extends Model
{
    /** @use HasFactory<ServiceCatalogItemFactory> */
    use HasFactory;

    /**
     * @return HasMany<BillItem, $this>
     */
    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => BillType::class,
            'unit_price_minor' => 'integer',
        ];
    }
}
