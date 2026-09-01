<?php

namespace App\Models;

use App\BillStatus;
use App\BillType;
use Carbon\CarbonImmutable;
use Database\Factories\FinancialClearanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $bill_id
 * @property string $clearance_number
 * @property int $granted_by_user_id
 * @property CarbonImmutable $granted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Bill $bill
 * @property-read User $grantedBy
 */
#[Fillable(['bill_id', 'granted_by_user_id'])]
class FinancialClearance extends Model
{
    /** @use HasFactory<FinancialClearanceFactory> */
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
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (FinancialClearance $financialClearance): void {
            $bill = Bill::query()
                ->with([
                    'items:id,bill_id,amount_minor',
                    'payment:id,bill_id,amount_minor',
                    'payment.receipt:id,payment_id',
                ])
                ->find($financialClearance->bill_id);

            if (! $bill instanceof Bill
                || $bill->type !== BillType::Consultation
                || $bill->status !== BillStatus::Paid
                || ! $bill->payment instanceof Payment
                || ! $bill->payment->receipt instanceof Receipt
                || $bill->payment->amount_minor !== $bill->totalAmountMinor()
                || $bill->payment->amount_minor <= 0) {
                throw new LogicException('Financial clearance requires a fully paid consultation Bill with a Receipt.');
            }

            if (! User::query()->whereKey($financialClearance->granted_by_user_id)->exists()) {
                throw new LogicException('Financial clearance requires a persisted granting user.');
            }

            $financialClearance->clearance_number = 'TMP-'.Str::ulid();
            $financialClearance->granted_at = now();
        });

        static::created(function (FinancialClearance $financialClearance): void {
            $financialClearance->clearance_number = self::clearanceNumberFor(
                (int) $financialClearance->getKey(),
            );
            $financialClearance->saveQuietly();
        });

        static::updating(function (FinancialClearance $financialClearance): void {
            if ($financialClearance->isDirty([
                'bill_id',
                'clearance_number',
                'granted_by_user_id',
                'granted_at',
            ])) {
                throw new LogicException('Financial clearance records cannot be changed.');
            }
        });
    }

    private static function clearanceNumberFor(int $id): string
    {
        return sprintf('CLR-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
        ];
    }
}
