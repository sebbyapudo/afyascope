<?php

namespace App\Models;

use App\BillStatus;
use App\BillType;
use App\PaymentMethod;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $bill_id
 * @property string $payment_number
 * @property int $amount_minor
 * @property PaymentMethod $method
 * @property int $recorded_by_user_id
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Bill $bill
 * @property-read User $recordedBy
 * @property-read Receipt|null $receipt
 */
#[Fillable(['bill_id', 'method', 'recorded_by_user_id'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return HasOne<Receipt, $this>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $bill = Bill::query()->with('items:id,bill_id,amount_minor')->find($payment->bill_id);

            if (! $bill instanceof Bill
                || ! in_array($bill->type, [BillType::Consultation, BillType::Procedure], true)
                || $bill->status !== BillStatus::Open) {
                throw new LogicException('Payments require an open Bill from a supported financial gate.');
            }

            $amountMinor = $bill->totalAmountMinor();

            if ($amountMinor <= 0) {
                throw new LogicException('Payments require a positive Bill total.');
            }

            if (! User::query()->whereKey($payment->recorded_by_user_id)->exists()) {
                throw new LogicException('Payments require a persisted recording user.');
            }

            $payment->payment_number = 'TMP-'.Str::ulid();
            $payment->amount_minor = $amountMinor;
            $payment->recorded_at = now();
        });

        static::created(function (Payment $payment): void {
            $payment->payment_number = self::paymentNumberFor((int) $payment->getKey());
            $payment->saveQuietly();
        });

        static::updating(function (Payment $payment): void {
            if ($payment->isDirty([
                'bill_id',
                'payment_number',
                'amount_minor',
                'method',
                'recorded_by_user_id',
                'recorded_at',
            ])) {
                throw new LogicException('Payment records cannot be changed.');
            }
        });
    }

    private static function paymentNumberFor(int $id): string
    {
        return sprintf('PAY-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'method' => PaymentMethod::class,
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
