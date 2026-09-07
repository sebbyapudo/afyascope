<?php

namespace App\Models;

use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PreProcedureReadinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $visit_id
 * @property int $procedure_decision_id
 * @property int $nurse_user_id
 * @property string $readiness_number
 * @property PreProcedureReadinessStatus $status
 * @property bool $consent_verified
 * @property bool $patient_identity_verified
 * @property bool $procedure_verified
 * @property bool $allergies_reviewed
 * @property bool $medications_reviewed
 * @property string|null $observations
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Visit $visit
 * @property-read ProcedureDecision $procedureDecision
 * @property-read User $nurse
 */
#[Fillable([
    'consent_verified',
    'patient_identity_verified',
    'procedure_verified',
    'allergies_reviewed',
    'medications_reviewed',
    'observations',
])]
class PreProcedureReadiness extends Model
{
    /** @use HasFactory<PreProcedureReadinessFactory> */
    use HasFactory;

    private static bool $startingFromNursingWorkflow = false;

    private static bool $updatingFromNursingWorkflow = false;

    private static bool $completingFromNursingWorkflow = false;

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

    /** @return BelongsTo<User, $this> */
    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_user_id');
    }

    public static function startForNursingWorkflow(
        Visit $visit,
        ProcedureDecision $procedureDecision,
        User $nurse,
    ): self {
        if (! $visit->exists
            || $visit->status !== VisitStatus::CheckedIn
            || ! $procedureDecision->exists
            || $procedureDecision->visit_id !== $visit->getKey()
            || $procedureDecision->outcome !== ProcedureDecisionOutcome::ProcedureRequired
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value) {
            throw new LogicException('Nursing preparation requires its checked-in procedure context and active Nurse.');
        }

        self::$startingFromNursingWorkflow = true;

        try {
            $readiness = new self;
            $readiness->visit()->associate($visit);
            $readiness->procedureDecision()->associate($procedureDecision);
            $readiness->nurse()->associate($nurse);
            $readiness->save();

            return $readiness;
        } finally {
            self::$startingFromNursingWorkflow = false;
        }
    }

    /** @param array<string, bool|string|null> $attributes */
    public function updateFromNursingWorkflow(User $nurse, array $attributes): void
    {
        if ($this->nurse_user_id !== $nurse->getKey()
            || $this->status !== PreProcedureReadinessStatus::InPreparation
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value) {
            throw new LogicException('Only the responsible active Nurse may update preparation.');
        }

        self::$updatingFromNursingWorkflow = true;

        try {
            $this->fill($attributes);
            $this->save();
        } finally {
            self::$updatingFromNursingWorkflow = false;
        }
    }

    public function completeFromNursingWorkflow(User $nurse): void
    {
        if ($this->nurse_user_id !== $nurse->getKey()
            || $this->status !== PreProcedureReadinessStatus::InPreparation
            || ! $this->hasAllMandatoryChecks()
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value) {
            throw new LogicException('Only the responsible active Nurse may complete verified readiness.');
        }

        self::$completingFromNursingWorkflow = true;

        try {
            $this->status = PreProcedureReadinessStatus::Ready;
            $this->completed_at = now();
            $this->save();
        } finally {
            self::$completingFromNursingWorkflow = false;
        }
    }

    public function hasAllMandatoryChecks(): bool
    {
        return $this->consent_verified
            && $this->patient_identity_verified
            && $this->procedure_verified
            && $this->allergies_reviewed
            && $this->medications_reviewed;
    }

    protected static function booted(): void
    {
        static::creating(function (PreProcedureReadiness $readiness): void {
            if (! self::$startingFromNursingWorkflow) {
                throw new LogicException(
                    'Pre-procedure readiness may only begin through the authoritative Nursing workflow.',
                );
            }

            $readiness->readiness_number = 'TMP-'.Str::ulid();
            $readiness->status = PreProcedureReadinessStatus::InPreparation;
            $readiness->started_at = now();
            $readiness->completed_at = null;
        });

        static::created(function (PreProcedureReadiness $readiness): void {
            $readiness->readiness_number = self::readinessNumberFor((int) $readiness->getKey());
            $readiness->saveQuietly();
        });

        static::updating(function (PreProcedureReadiness $readiness): void {
            if ($readiness->getRawOriginal('status') === PreProcedureReadinessStatus::Ready->value
                && $readiness->isDirty()) {
                throw new LogicException('Completed pre-procedure readiness records cannot be changed.');
            }

            if ($readiness->isDirty([
                'visit_id',
                'procedure_decision_id',
                'nurse_user_id',
                'readiness_number',
                'started_at',
            ])) {
                throw new LogicException('Pre-procedure readiness ownership and context cannot be changed.');
            }

            if ($readiness->isDirty(['status', 'completed_at']) && ! self::$completingFromNursingWorkflow) {
                throw new LogicException('Readiness completion requires its authoritative Nursing workflow.');
            }

            if ($readiness->isDirty([
                'consent_verified',
                'patient_identity_verified',
                'procedure_verified',
                'allergies_reviewed',
                'medications_reviewed',
                'observations',
            ]) && ! self::$updatingFromNursingWorkflow) {
                throw new LogicException('Readiness checks require their authoritative Nursing workflow.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Pre-procedure readiness records cannot be deleted.');
        });
    }

    private static function readinessNumberFor(int $id): string
    {
        return sprintf('PPR-%06d', $id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PreProcedureReadinessStatus::class,
            'consent_verified' => 'boolean',
            'patient_identity_verified' => 'boolean',
            'procedure_verified' => 'boolean',
            'allergies_reviewed' => 'boolean',
            'medications_reviewed' => 'boolean',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
