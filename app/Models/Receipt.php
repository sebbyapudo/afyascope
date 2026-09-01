<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $payment_id
 * @property string $receipt_number
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Payment $payment
 */
#[Fillable(['payment_id'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Receipt $receipt): void {
            if (! Payment::query()->whereKey($receipt->payment_id)->exists()) {
                throw new LogicException('Receipts require a persisted Payment.');
            }

            $receipt->receipt_number = 'TMP-'.Str::ulid();
            $receipt->issued_at = now();
        });

        static::created(function (Receipt $receipt): void {
            $receipt->receipt_number = self::receiptNumberFor((int) $receipt->getKey());
            $receipt->saveQuietly();
        });

        static::updating(function (Receipt $receipt): void {
            if ($receipt->isDirty(['payment_id', 'receipt_number', 'issued_at'])) {
                throw new LogicException('Receipt records cannot be changed.');
            }
        });
    }

    private static function receiptNumberFor(int $id): string
    {
        return sprintf('RCT-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
        ];
    }
}
