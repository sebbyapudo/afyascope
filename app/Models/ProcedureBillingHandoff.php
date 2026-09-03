<?php

namespace App\Models;

use App\BillType;
use App\ConsultationStatus;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
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
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int|null $procedure_decision_id
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
 * @property-read ProcedureDecision|null $procedureDecision
 */
#[Fillable(['visit_id', 'service_catalog_item_id', 'decided_by_user_id'])]
class ProcedureBillingHandoff extends Model
{
    /** @use HasFactory<ProcedureBillingHandoffFactory> */
    use HasFactory;

    private static bool $creatingFromProcedureDecision = false;

    /** @return BelongsTo<ProcedureDecision, $this> */
    public function procedureDecision(): BelongsTo
    {
        return $this->belongsTo(ProcedureDecision::class);
    }

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

    public static function createFromProcedureDecision(ProcedureDecision $decision): self
    {
        $consultation = $decision->consultation;
        $doctor = $decision->doctor;
        $serviceCatalogItem = $decision->serviceCatalogItem;
        $visit = $decision->visit;

        if (! $decision->exists
            || $decision->outcome !== ProcedureDecisionOutcome::ProcedureRequired
            || ! $serviceCatalogItem instanceof ServiceCatalogItem
            || ! $serviceCatalogItem->is_active
            || $serviceCatalogItem->category !== BillType::Procedure
            || $consultation->status !== ConsultationStatus::InProgress
            || $consultation->visit_id !== $visit->getKey()
            || $consultation->doctor_user_id !== $doctor->getKey()
            || ! $doctor->is_active
            || $doctor->role?->slug !== StaffRole::Doctor->value
            || $visit->status !== VisitStatus::CheckedIn
            || ! $visit->checkIn instanceof VisitCheckIn) {
            throw new LogicException('A procedure billing handoff requires an authoritative procedure-required decision.');
        }

        self::$creatingFromProcedureDecision = true;

        try {
            $handoff = new self;
            $handoff->procedureDecision()->associate($decision);
            $handoff->visit()->associate($decision->visit_id);
            $handoff->serviceCatalogItem()->associate($decision->service_catalog_item_id);
            $handoff->decidedBy()->associate($decision->doctor_user_id);
            $handoff->save();

            return $handoff;
        } finally {
            self::$creatingFromProcedureDecision = false;
        }
    }

    /**
     * @param  Builder<ProcedureBillingHandoff>  $query
     * @return Builder<ProcedureBillingHandoff>
     */
    #[Scope]
    protected function awaitingBill(Builder $query): Builder
    {
        return $query
            ->whereNotNull('procedure_decision_id')
            ->whereDoesntHave('bill')
            ->whereHas('procedureDecision', function (Builder $decisionQuery): void {
                $decisionQuery->where('outcome', ProcedureDecisionOutcome::ProcedureRequired->value);
            })
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
            if (! self::$creatingFromProcedureDecision) {
                throw new LogicException(
                    'Procedure billing handoffs require the authoritative Doctor procedure-decision workflow.',
                );
            }

            $handoff->handoff_number = 'TMP-'.Str::ulid();
            $handoff->decided_at = $handoff->procedureDecision->decided_at;
        });

        static::created(function (ProcedureBillingHandoff $handoff): void {
            $handoff->handoff_number = self::handoffNumberFor((int) $handoff->getKey());
            $handoff->saveQuietly();
        });

        static::updating(function (ProcedureBillingHandoff $handoff): void {
            if ($handoff->isDirty([
                'visit_id',
                'procedure_decision_id',
                'service_catalog_item_id',
                'decided_by_user_id',
                'handoff_number',
                'decided_at',
            ])) {
                throw new LogicException('Procedure billing handoffs cannot be changed.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Procedure billing handoffs cannot be deleted.');
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
