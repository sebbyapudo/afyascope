<?php

namespace App\Models;

use App\RecoveryEpisodeStatus;
use App\StaffRole;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\RecoveryEpisodeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $visit_id
 * @property int $procedure_record_id
 * @property int $nurse_user_id
 * @property string $recovery_number
 * @property RecoveryEpisodeStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Visit $visit
 * @property-read ProcedureRecord $procedureRecord
 * @property-read User $nurse
 * @property-read Collection<int, RecoveryObservation> $observations
 */
class RecoveryEpisode extends Model
{
    /** @use HasFactory<RecoveryEpisodeFactory> */
    use HasFactory;

    private static bool $startingFromNursingWorkflow = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => RecoveryEpisodeStatus::InProgress->value,
    ];

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<ProcedureRecord, $this> */
    public function procedureRecord(): BelongsTo
    {
        return $this->belongsTo(ProcedureRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_user_id');
    }

    /** @return HasMany<RecoveryObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(RecoveryObservation::class);
    }

    public static function startFromNursingWorkflow(
        ProcedureRecord $procedureRecord,
        Visit $visit,
        User $nurse,
    ): self {
        if (! $procedureRecord->exists
            || ! $procedureRecord->isReadyForNursingRecovery()
            || ! $visit->exists
            || $procedureRecord->visit_id !== $visit->getKey()
            || $visit->status !== VisitStatus::CheckedIn
            || ! $nurse->exists
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value) {
            throw new LogicException('Recovery requires a completed Procedure Record, its checked-in Visit, and an active Nurse.');
        }

        self::$startingFromNursingWorkflow = true;

        try {
            $recoveryEpisode = new self;
            $recoveryEpisode->visit()->associate($visit);
            $recoveryEpisode->procedureRecord()->associate($procedureRecord);
            $recoveryEpisode->nurse()->associate($nurse);
            $recoveryEpisode->save();

            return $recoveryEpisode;
        } finally {
            self::$startingFromNursingWorkflow = false;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (RecoveryEpisode $recoveryEpisode): void {
            if (! self::$startingFromNursingWorkflow) {
                throw new LogicException(
                    'Recovery episodes may only begin through the authoritative Nursing workflow.',
                );
            }

            $recoveryEpisode->recovery_number = 'TMP-'.Str::ulid();
            $recoveryEpisode->status = RecoveryEpisodeStatus::InProgress;
            $recoveryEpisode->started_at = now();
            $recoveryEpisode->completed_at = null;
        });

        static::created(function (RecoveryEpisode $recoveryEpisode): void {
            $recoveryEpisode->recovery_number = sprintf('REC-%06d', $recoveryEpisode->id);
            $recoveryEpisode->saveQuietly();
        });

        static::updating(function (): void {
            throw new LogicException(
                'Recovery episode state and authoritative context require the future Nursing workflow.',
            );
        });

        static::deleting(function (): void {
            throw new LogicException('Recovery episodes cannot be deleted.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RecoveryEpisodeStatus::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
