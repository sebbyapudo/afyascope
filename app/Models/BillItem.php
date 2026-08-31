<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BillItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $bill_id
 * @property int $service_catalog_item_id
 * @property string $description
 * @property int $amount_minor
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Bill $bill
 * @property-read ServiceCatalogItem $serviceCatalogItem
 */
#[Fillable(['bill_id', 'service_catalog_item_id'])]
class BillItem extends Model
{
    /** @use HasFactory<BillItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<ServiceCatalogItem, $this>
     */
    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (BillItem $billItem): void {
            $bill = Bill::query()->find($billItem->bill_id);
            $serviceCatalogItem = ServiceCatalogItem::query()->find($billItem->service_catalog_item_id);

            if ($bill === null || $serviceCatalogItem === null) {
                throw new LogicException('Bill items require a persisted Bill and service catalog item.');
            }

            if ($bill->type !== $serviceCatalogItem->category) {
                throw new LogicException('The service category must match the Bill type.');
            }

            $billItem->description = $serviceCatalogItem->name;
            $billItem->amount_minor = $serviceCatalogItem->unit_price_minor;
        });

        static::updating(function (BillItem $billItem): void {
            if ($billItem->isDirty([
                'bill_id',
                'service_catalog_item_id',
                'description',
                'amount_minor',
            ])) {
                throw new LogicException('Bill item snapshots cannot be changed.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }
}
