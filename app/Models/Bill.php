<?php

namespace App\Models;

use App\BillStatus;
use App\BillType;
use Carbon\CarbonImmutable;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $visit_id
 * @property string $bill_number
 * @property BillType $type
 * @property BillStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Visit $visit
 * @property-read Collection<int, BillItem> $items
 * @property-read Payment|null $payment
 */
#[Fillable(['visit_id', 'type'])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => BillStatus::Open->value,
    ];

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return HasMany<BillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function totalAmountMinor(): int
    {
        if ($this->relationLoaded('items')) {
            return (int) $this->items->sum('amount_minor');
        }

        return (int) $this->items()->sum('amount_minor');
    }

    protected static function booted(): void
    {
        static::creating(function (Bill $bill): void {
            $bill->bill_number = 'TMP-'.Str::ulid();
            $bill->status = BillStatus::Open;
        });

        static::created(function (Bill $bill): void {
            $bill->bill_number = self::billNumberFor((int) $bill->getKey());
            $bill->saveQuietly();
        });

        static::updating(function (Bill $bill): void {
            if ($bill->isDirty('bill_number')) {
                throw new LogicException('Bill numbers cannot be changed.');
            }

            if ($bill->isDirty('visit_id') || $bill->isDirty('type')) {
                throw new LogicException('A Bill cannot be reassigned to another Visit or billing gate.');
            }
        });
    }

    private static function billNumberFor(int $id): string
    {
        return sprintf('BIL-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BillStatus::class,
            'type' => BillType::class,
        ];
    }
}
