<?php

namespace App\Models;

use App\BillType;
use App\ConsultationStatus;
use App\ProcedureDecisionOutcome;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int $patient_id
 * @property int|null $appointment_id
 * @property string $visit_number
 * @property CarbonImmutable $occurred_at
 * @property VisitStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Appointment|null $appointment
 * @property-read Collection<int, Bill> $bills
 * @property-read Bill|null $consultationBill
 * @property-read Bill|null $procedureBill
 * @property-read Consultation|null $consultation
 * @property-read ProcedureBillingHandoff|null $procedureBillingHandoff
 * @property-read ProcedureDecision|null $procedureDecision
 * @property-read Patient $patient
 * @property-read VisitCheckIn|null $checkIn
 */
#[Fillable(['patient_id', 'occurred_at'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => VisitStatus::Created->value,
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * @return HasOne<Bill, $this>
     */
    public function consultationBill(): HasOne
    {
        return $this->hasOne(Bill::class)
            ->where('type', BillType::Consultation->value);
    }

    /**
     * @return HasOne<Bill, $this>
     */
    public function procedureBill(): HasOne
    {
        return $this->hasOne(Bill::class)
            ->where('type', BillType::Procedure->value);
    }

    /**
     * @return HasOne<Consultation, $this>
     */
    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    /**
     * @return HasOne<ProcedureBillingHandoff, $this>
     */
    public function procedureBillingHandoff(): HasOne
    {
        return $this->hasOne(ProcedureBillingHandoff::class);
    }

    /** @return HasOne<ProcedureDecision, $this> */
    public function procedureDecision(): HasOne
    {
        return $this->hasOne(ProcedureDecision::class);
    }

    /**
     * @return HasOne<VisitCheckIn, $this>
     */
    public function checkIn(): HasOne
    {
        return $this->hasOne(VisitCheckIn::class);
    }

    public function isReadyForDoctorConsultation(): bool
    {
        if ($this->status !== VisitStatus::CheckedIn) {
            return false;
        }

        $hasCheckIn = $this->relationLoaded('checkIn')
            ? $this->checkIn instanceof VisitCheckIn
            : $this->checkIn()->exists();
        $hasConsultation = $this->relationLoaded('consultation')
            ? $this->consultation instanceof Consultation
            : $this->consultation()->exists();

        return $hasCheckIn && ! $hasConsultation;
    }

    public function isInConsultation(): bool
    {
        if ($this->status !== VisitStatus::CheckedIn) {
            return false;
        }

        $hasCheckIn = $this->relationLoaded('checkIn')
            ? $this->checkIn instanceof VisitCheckIn
            : $this->checkIn()->exists();
        $consultation = $this->relationLoaded('consultation')
            ? $this->consultation
            : $this->consultation()->first();

        return $hasCheckIn
            && $consultation instanceof Consultation
            && $consultation->status === ConsultationStatus::InProgress;
    }

    /**
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    #[Scope]
    protected function readyForDoctorConsultation(Builder $query): Builder
    {
        return $query
            ->where('status', VisitStatus::CheckedIn->value)
            ->whereHas('checkIn')
            ->whereDoesntHave('consultation');
    }

    /**
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    #[Scope]
    protected function inConsultation(Builder $query): Builder
    {
        return $query
            ->where('status', VisitStatus::CheckedIn->value)
            ->whereHas('checkIn')
            ->whereHas('consultation', function (Builder $consultationQuery): void {
                $consultationQuery->where('status', ConsultationStatus::InProgress->value);
            });
    }

    public function workflowMessage(): string
    {
        if ($this->status === VisitStatus::CheckedIn) {
            $consultation = $this->relationLoaded('consultation')
                ? $this->consultation
                : $this->consultation()->first();

            if ($consultation?->status === ConsultationStatus::InProgress) {
                $procedureDecision = $this->relationLoaded('procedureDecision')
                    ? $this->procedureDecision
                    : $this->procedureDecision()->first();

                if ($procedureDecision?->outcome === ProcedureDecisionOutcome::ProcedureRequired) {
                    $procedureBill = $this->relationLoaded('procedureBill')
                        ? $this->procedureBill
                        : $this->procedureBill()->with([
                            'payment:id,bill_id',
                            'payment.receipt:id,payment_id',
                            'financialClearance:id,bill_id',
                        ])->first();

                    if (! $procedureBill instanceof Bill) {
                        return 'Awaiting procedure billing';
                    }

                    $payment = $procedureBill->relationLoaded('payment')
                        ? $procedureBill->payment
                        : $procedureBill->payment()->with('receipt:id,payment_id')->first();

                    if (! $payment instanceof Payment || ! $payment->receipt instanceof Receipt) {
                        return 'Awaiting procedure payment';
                    }

                    $hasFinancialClearance = $procedureBill->relationLoaded('financialClearance')
                        ? $procedureBill->financialClearance instanceof FinancialClearance
                        : $procedureBill->financialClearance()->exists();

                    return $hasFinancialClearance
                        ? 'Ready for Nursing preparation'
                        : 'Awaiting procedure financial clearance';
                }

                return $procedureDecision?->outcome === ProcedureDecisionOutcome::NoProcedure
                    ? 'No procedure required'
                    : 'Consultation in progress';
            }

            return $consultation?->status === ConsultationStatus::Finalized
                ? 'Consultation completed'
                : VisitStatus::CheckedIn->handoffLabel();
        }

        $consultationBill = $this->relationLoaded('consultationBill')
            ? $this->consultationBill
            : $this->consultationBill()->with([
                'payment:id,bill_id',
                'financialClearance:id,bill_id',
            ])->first();

        if ($consultationBill instanceof Bill) {
            $hasPayment = $consultationBill->relationLoaded('payment')
                ? $consultationBill->payment instanceof Payment
                : $consultationBill->payment()->exists();

            if (! $hasPayment) {
                return 'Awaiting consultation payment';
            }

            $hasFinancialClearance = $consultationBill->relationLoaded('financialClearance')
                ? $consultationBill->financialClearance instanceof FinancialClearance
                : $consultationBill->financialClearance()->exists();

            return $hasFinancialClearance
                ? 'Awaiting Reception check-in'
                : 'Awaiting consultation financial clearance';
        }

        return $this->status->handoffLabel();
    }

    protected static function booted(): void
    {
        static::creating(function (Visit $visit): void {
            $visit->visit_number = 'TMP-'.Str::ulid();
            $visit->status = VisitStatus::Created;
        });

        static::created(function (Visit $visit): void {
            $visit->visit_number = self::visitNumberFor((int) $visit->getKey());
            $visit->saveQuietly();
        });

        static::updating(function (Visit $visit): void {
            if ($visit->isDirty('visit_number')) {
                throw new LogicException('Visit numbers cannot be changed.');
            }

            if ($visit->isDirty('appointment_id')) {
                throw new LogicException('A Visit appointment linkage cannot be changed.');
            }

            if ($visit->isDirty('status')) {
                $isCheckInTransition = $visit->getRawOriginal('status') === VisitStatus::Created->value
                    && $visit->status === VisitStatus::CheckedIn
                    && $visit->checkIn()->exists();

                if (! $isCheckInTransition) {
                    throw new LogicException('Visit status can only change through its owning workflow action.');
                }
            }
        });
    }

    private static function visitNumberFor(int $id): string
    {
        return sprintf('VIS-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'status' => VisitStatus::class,
        ];
    }
}
