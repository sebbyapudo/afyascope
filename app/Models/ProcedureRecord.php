<?php

namespace App\Models;

use App\ConsultationStatus;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\StaffRole;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProcedureRecordFactory;
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
 * @property int $visit_id
 * @property int $procedure_decision_id
 * @property int $pre_procedure_readiness_id
 * @property int $service_catalog_item_id
 * @property int $doctor_user_id
 * @property string $procedure_number
 * @property ProcedureRecordStatus $status
 * @property string|null $findings
 * @property string|null $diagnosis_impression
 * @property bool $specimens_taken
 * @property string|null $specimen_notes
 * @property string|null $complications
 * @property string|null $outcome
 * @property string|null $procedure_notes
 * @property int $lock_version
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Visit $visit
 * @property-read ProcedureDecision $procedureDecision
 * @property-read PreProcedureReadiness $preProcedureReadiness
 * @property-read ServiceCatalogItem $serviceCatalogItem
 * @property-read User $doctor
 * @property-read RecoveryEpisode|null $recoveryEpisode
 */
#[Fillable([
    'findings',
    'diagnosis_impression',
    'specimens_taken',
    'specimen_notes',
    'complications',
    'outcome',
    'procedure_notes',
])]
class ProcedureRecord extends Model
{
    /** @use HasFactory<ProcedureRecordFactory> */
    use HasFactory;

    private static bool $startingFromDoctorWorkflow = false;

    private static bool $updatingFromDoctorWorkflow = false;

    private static bool $completingFromDoctorWorkflow = false;

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<ProcedureDecision, $this> */
    public function procedureDecision(): BelongsTo
    {
        return $this->belongsTo(ProcedureDecision::class);
    }

    /** @return BelongsTo<PreProcedureReadiness, $this> */
    public function preProcedureReadiness(): BelongsTo
    {
        return $this->belongsTo(PreProcedureReadiness::class);
    }

    /** @return BelongsTo<ServiceCatalogItem, $this> */
    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    /** @return HasOne<RecoveryEpisode, $this> */
    public function recoveryEpisode(): HasOne
    {
        return $this->hasOne(RecoveryEpisode::class);
    }

    public static function startFromDoctorWorkflow(
        Visit $visit,
        ProcedureDecision $procedureDecision,
        PreProcedureReadiness $preProcedureReadiness,
        ServiceCatalogItem $serviceCatalogItem,
        User $doctor,
    ): self {
        if (! $visit->exists
            || $visit->status !== VisitStatus::CheckedIn
            || ! $procedureDecision->exists
            || $procedureDecision->visit_id !== $visit->getKey()
            || $procedureDecision->outcome !== ProcedureDecisionOutcome::ProcedureRequired
            || $procedureDecision->service_catalog_item_id !== $serviceCatalogItem->getKey()
            || $procedureDecision->doctor_user_id !== $doctor->getKey()
            || $procedureDecision->consultation->status !== ConsultationStatus::InProgress
            || ! $preProcedureReadiness->exists
            || $preProcedureReadiness->visit_id !== $visit->getKey()
            || $preProcedureReadiness->procedure_decision_id !== $procedureDecision->getKey()
            || $preProcedureReadiness->status !== PreProcedureReadinessStatus::Ready
            || ! $doctor->is_active
            || $doctor->role?->slug !== StaffRole::Doctor->value) {
            throw new LogicException('A procedure requires its ready clinical handoff and responsible active Doctor.');
        }

        self::$startingFromDoctorWorkflow = true;

        try {
            $procedureRecord = new self;
            $procedureRecord->visit()->associate($visit);
            $procedureRecord->procedureDecision()->associate($procedureDecision);
            $procedureRecord->preProcedureReadiness()->associate($preProcedureReadiness);
            $procedureRecord->serviceCatalogItem()->associate($serviceCatalogItem);
            $procedureRecord->doctor()->associate($doctor);
            $procedureRecord->save();

            return $procedureRecord;
        } finally {
            self::$startingFromDoctorWorkflow = false;
        }
    }

    /** @param array<string, bool|string|null> $attributes */
    public function updateDocumentationFromDoctorWorkflow(User $doctor, array $attributes): void
    {
        if ($this->doctor_user_id !== $doctor->getKey()
            || $this->status !== ProcedureRecordStatus::InProgress
            || ! $doctor->is_active
            || $doctor->role?->slug !== StaffRole::Doctor->value) {
            throw new LogicException('Only the responsible active Doctor may update procedure documentation.');
        }

        self::$updatingFromDoctorWorkflow = true;

        try {
            $this->fill($attributes);
            $this->lock_version++;
            $this->save();
        } finally {
            self::$updatingFromDoctorWorkflow = false;
        }
    }

    public function completeFromDoctorWorkflow(User $doctor): void
    {
        if ($this->doctor_user_id !== $doctor->getKey()
            || $this->status !== ProcedureRecordStatus::InProgress
            || ! $this->hasRequiredCompletionDocumentation()
            || ! $doctor->is_active
            || $doctor->role?->slug !== StaffRole::Doctor->value) {
            throw new LogicException('Only the responsible active Doctor may complete documented procedure care.');
        }

        self::$completingFromDoctorWorkflow = true;

        try {
            $this->status = ProcedureRecordStatus::Completed;
            $this->completed_at = now();
            $this->lock_version++;
            $this->save();
        } finally {
            self::$completingFromDoctorWorkflow = false;
        }
    }

    public function hasRequiredCompletionDocumentation(): bool
    {
        return filled($this->findings)
            && filled($this->outcome)
            && (! $this->specimens_taken || filled($this->specimen_notes));
    }

    public function isReadyForNursingRecovery(): bool
    {
        return $this->status === ProcedureRecordStatus::Completed;
    }

    /**
     * @param  Builder<ProcedureRecord>  $query
     * @return Builder<ProcedureRecord>
     */
    #[Scope]
    protected function readyForNursingRecovery(Builder $query): Builder
    {
        return $query
            ->where('status', ProcedureRecordStatus::Completed->value)
            ->whereHas('visit', function (Builder $visitQuery): void {
                $visitQuery->where('status', VisitStatus::CheckedIn->value);
            })
            ->whereDoesntHave('recoveryEpisode');
    }

    protected static function booted(): void
    {
        static::creating(function (ProcedureRecord $procedureRecord): void {
            if (! self::$startingFromDoctorWorkflow) {
                throw new LogicException(
                    'Procedure records may only begin through the authoritative Doctor workflow.',
                );
            }

            $procedureRecord->procedure_number = 'TMP-'.Str::ulid();
            $procedureRecord->status = ProcedureRecordStatus::InProgress;
            $procedureRecord->lock_version = 1;
            $procedureRecord->started_at = now();
            $procedureRecord->completed_at = null;
        });

        static::created(function (ProcedureRecord $procedureRecord): void {
            $procedureRecord->procedure_number = self::procedureNumberFor((int) $procedureRecord->getKey());
            $procedureRecord->saveQuietly();
        });

        static::updating(function (ProcedureRecord $procedureRecord): void {
            if ($procedureRecord->getRawOriginal('status') === ProcedureRecordStatus::Completed->value
                && $procedureRecord->isDirty()) {
                throw new LogicException('Completed procedure records cannot be changed.');
            }

            if ($procedureRecord->isDirty([
                'visit_id',
                'procedure_decision_id',
                'pre_procedure_readiness_id',
                'service_catalog_item_id',
                'doctor_user_id',
                'procedure_number',
                'started_at',
            ])) {
                throw new LogicException('Procedure ownership and authoritative context cannot be changed.');
            }

            if ($procedureRecord->isDirty(['status', 'completed_at'])
                && ! self::$completingFromDoctorWorkflow) {
                throw new LogicException('Procedure completion requires its authoritative Doctor workflow.');
            }

            if ($procedureRecord->isDirty([
                'findings',
                'diagnosis_impression',
                'specimens_taken',
                'specimen_notes',
                'complications',
                'outcome',
                'procedure_notes',
            ]) && ! self::$updatingFromDoctorWorkflow) {
                throw new LogicException('Procedure documentation requires its authoritative Doctor workflow.');
            }

            if ($procedureRecord->isDirty('lock_version')
                && ! self::$updatingFromDoctorWorkflow
                && ! self::$completingFromDoctorWorkflow) {
                throw new LogicException('Procedure concurrency state is server-controlled.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Procedure records cannot be deleted.');
        });
    }

    private static function procedureNumberFor(int $id): string
    {
        return sprintf('PRC-%06d', $id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProcedureRecordStatus::class,
            'specimens_taken' => 'boolean',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
