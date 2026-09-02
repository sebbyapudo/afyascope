<?php

namespace App\Models;

use App\BillType;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProcedureBillingHandoffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * @property int $id
 * @property int $visit_id
 * @property int $service_catalog_item_id
 * @property int $decided_by_user_id
 * @property string $handoff_number
 * @property CarbonImmutable $decided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Visit $visit
 * @property-read ServiceCatalogItem $serviceCatalogItem
 * @property-read User $decidedBy
 * @property-read Bill|null $bill
 */
#[Fillable(['visit_id', 'service_catalog_item_id', 'decided_by_user_id'])]
class ProcedureBillingHandoff extends Model
{
    /** @use HasFactory<ProcedureBillingHandoffFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<ServiceCatalogItem, $this>
     */
    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * @return HasOne<Bill, $this>
     */
    public function bill(): HasOne
    {
        return $this->hasOne(Bill::class);
    }

    /**
     * @param  Builder<ProcedureBillingHandoff>  $query
     * @return Builder<ProcedureBillingHandoff>
     */
    #[Scope]
    protected function awaitingBill(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('bill')
            ->whereHas('visit', function (Builder $visitQuery): void {
                $visitQuery
                    ->where('status', VisitStatus::CheckedIn->value)
                    ->whereHas('checkIn');
            })
            ->whereHas('serviceCatalogItem', function (Builder $serviceQuery): void {
                $serviceQuery
                    ->where('category', BillType::Procedure->value)
                    ->where('is_active', true);
            })
            ->orderBy('decided_at')
            ->orderBy('id');
    }

    protected static function booted(): void
    {
        static::creating(function (ProcedureBillingHandoff $handoff): void {
            throw new LogicException(
                'Procedure billing handoffs are reserved for the future authoritative Doctor procedure-decision workflow.',
            );
        });

        static::created(function (ProcedureBillingHandoff $handoff): void {
            $handoff->handoff_number = self::handoffNumberFor((int) $handoff->getKey());
            $handoff->saveQuietly();
        });

        static::updating(function (ProcedureBillingHandoff $handoff): void {
            if ($handoff->isDirty([
                'visit_id',
                'service_catalog_item_id',
                'decided_by_user_id',
                'handoff_number',
                'decided_at',
            ])) {
                throw new LogicException('Procedure billing handoffs cannot be changed.');
            }
        });
    }

    private static function handoffNumberFor(int $id): string
    {
        return sprintf('PBH-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decided_at' => 'immutable_datetime',
        ];
    }
}
