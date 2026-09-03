<?php

namespace App\Models;

use App\BillType;
use App\ConsultationStatus;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProcedureDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $consultation_id
 * @property int $visit_id
 * @property int $doctor_user_id
 * @property int|null $service_catalog_item_id
 * @property string $decision_number
 * @property ProcedureDecisionOutcome $outcome
 * @property string|null $clinical_rationale
 * @property CarbonImmutable $decided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Consultation $consultation
 * @property-read Visit $visit
 * @property-read User $doctor
 * @property-read ServiceCatalogItem|null $serviceCatalogItem
 * @property-read ProcedureBillingHandoff|null $procedureBillingHandoff
 */
#[Fillable(['clinical_rationale'])]
class ProcedureDecision extends Model
{
    /** @use HasFactory<ProcedureDecisionFactory> */
    use HasFactory;

    private static bool $recordingAuthoritativeDecision = false;

    /** @return BelongsTo<Consultation, $this> */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    /** @return BelongsTo<ServiceCatalogItem, $this> */
    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class);
    }

    /** @return HasOne<ProcedureBillingHandoff, $this> */
    public function procedureBillingHandoff(): HasOne
    {
        return $this->hasOne(ProcedureBillingHandoff::class);
    }

    public static function recordFromAuthoritativeDoctorWorkflow(
        Consultation $consultation,
        User $doctor,
        ProcedureDecisionOutcome $outcome,
        ?ServiceCatalogItem $serviceCatalogItem,
        ?string $clinicalRationale,
    ): self {
        if (! $consultation->exists
            || $consultation->status !== ConsultationStatus::InProgress
            || $consultation->doctor_user_id !== $doctor->getKey()
            || $consultation->visit->status !== VisitStatus::CheckedIn
            || ! $consultation->visit->checkIn instanceof VisitCheckIn
            || ! $doctor->is_active
            || $doctor->role?->slug !== StaffRole::Doctor->value) {
            throw new LogicException('A procedure decision requires its responsible in-progress Doctor Consultation.');
        }

        if (($outcome === ProcedureDecisionOutcome::ProcedureRequired) !== ($serviceCatalogItem instanceof ServiceCatalogItem)) {
            throw new LogicException('The selected procedure service must match the procedure decision outcome.');
        }

        if ($serviceCatalogItem instanceof ServiceCatalogItem
            && (! $serviceCatalogItem->is_active || $serviceCatalogItem->category !== BillType::Procedure)) {
            throw new LogicException('A procedure decision requires an active procedure service.');
        }

        self::$recordingAuthoritativeDecision = true;

        try {
            $decision = new self;
            $decision->consultation()->associate($consultation);
            $decision->visit()->associate($consultation->visit_id);
            $decision->doctor()->associate($doctor);
            $decision->serviceCatalogItem()->associate($serviceCatalogItem);
            $decision->outcome = $outcome;
            $decision->clinical_rationale = $clinicalRationale;
            $decision->save();

            return $decision;
        } finally {
            self::$recordingAuthoritativeDecision = false;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (ProcedureDecision $decision): void {
            if (! self::$recordingAuthoritativeDecision) {
                throw new LogicException(
                    'Procedure decisions may only be recorded through the authoritative Doctor workflow.',
                );
            }

            $decision->decision_number = 'TMP-'.Str::ulid();
            $decision->decided_at = now();
        });

        static::created(function (ProcedureDecision $decision): void {
            $decision->decision_number = self::decisionNumberFor((int) $decision->getKey());
            $decision->saveQuietly();
        });

        static::updating(function (ProcedureDecision $decision): void {
            if ($decision->isDirty()) {
                throw new LogicException('Procedure decisions cannot be changed.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Procedure decisions cannot be deleted.');
        });
    }

    private static function decisionNumberFor(int $id): string
    {
        return sprintf('PDC-%06d', $id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'outcome' => ProcedureDecisionOutcome::class,
            'decided_at' => 'immutable_datetime',
        ];
    }
}
