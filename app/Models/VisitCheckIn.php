<?php

namespace App\Models;

use App\BillStatus;
use App\BillType;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VisitCheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $visit_id
 * @property string $check_in_number
 * @property int $checked_in_by_user_id
 * @property CarbonImmutable $checked_in_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Visit $visit
 * @property-read User $checkedInBy
 */
#[Fillable(['visit_id', 'checked_in_by_user_id'])]
class VisitCheckIn extends Model
{
    /** @use HasFactory<VisitCheckInFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (VisitCheckIn $visitCheckIn): void {
            $visit = Visit::query()
                ->with([
                    'consultationBill.items:id,bill_id,amount_minor',
                    'consultationBill.payment:id,bill_id,amount_minor',
                    'consultationBill.payment.receipt:id,payment_id',
                    'consultationBill.financialClearance:id,bill_id',
                ])
                ->find($visitCheckIn->visit_id);
            $bill = $visit?->consultationBill;
            $payment = $bill?->payment;

            if (! $visit instanceof Visit
                || $visit->status !== VisitStatus::Created
                || ! $bill instanceof Bill
                || $bill->type !== BillType::Consultation
                || $bill->status !== BillStatus::Paid
                || ! $payment instanceof Payment
                || ! $payment->receipt instanceof Receipt
                || ! $bill->financialClearance instanceof FinancialClearance
                || $payment->amount_minor <= 0
                || $payment->amount_minor !== $bill->totalAmountMinor()) {
                throw new LogicException('Check-in requires a created Visit with a paid and financially cleared consultation Bill.');
            }

            if (! User::query()->whereKey($visitCheckIn->checked_in_by_user_id)->exists()) {
                throw new LogicException('Check-in requires a persisted Receptionist.');
            }

            $visitCheckIn->check_in_number = 'TMP-'.Str::ulid();
            $visitCheckIn->checked_in_at = now();
        });

        static::created(function (VisitCheckIn $visitCheckIn): void {
            $visitCheckIn->check_in_number = self::checkInNumberFor((int) $visitCheckIn->getKey());
            $visitCheckIn->saveQuietly();
        });

        static::updating(function (VisitCheckIn $visitCheckIn): void {
            if ($visitCheckIn->isDirty([
                'visit_id',
                'check_in_number',
                'checked_in_by_user_id',
                'checked_in_at',
            ])) {
                throw new LogicException('Visit check-in records cannot be changed.');
            }
        });
    }

    private static function checkInNumberFor(int $id): string
    {
        return sprintf('CHK-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'immutable_datetime',
        ];
    }
}
