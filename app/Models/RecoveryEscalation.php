<?php

namespace App\Models;

use App\RecoveryEpisodeStatus;
use App\RecoveryEscalationResolution;
use App\RecoveryEscalationStatus;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Database\Factories\RecoveryEscalationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $recovery_episode_id
 * @property int $escalated_by_user_id
 * @property string $reason
 * @property RecoveryEscalationStatus $status
 * @property bool|null $open_marker
 * @property CarbonImmutable $escalated_at
 * @property int|null $resolved_by_user_id
 * @property RecoveryEscalationResolution|null $resolution
 * @property string|null $resolution_note
 * @property CarbonImmutable|null $resolved_at
 * @property-read RecoveryEpisode $recoveryEpisode
 * @property-read User $escalatedBy
 * @property-read User|null $resolvedBy
 */
#[Guarded(['*'])]
class RecoveryEscalation extends Model
{
    /** @use HasFactory<RecoveryEscalationFactory> */
    use HasFactory;

    private static bool $writingFromWorkflow = false;

    /** @return BelongsTo<RecoveryEpisode, $this> */
    public function recoveryEpisode(): BelongsTo
    {
        return $this->belongsTo(RecoveryEpisode::class);
    }

    /** @return BelongsTo<User, $this> */
    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public static function escalateFromNursingWorkflow(
        RecoveryEpisode $recoveryEpisode,
        User $nurse,
        string $reason,
    ): self {
        if (! $recoveryEpisode->exists
            || $recoveryEpisode->status !== RecoveryEpisodeStatus::InProgress
            || $recoveryEpisode->nurse_user_id !== $nurse->getKey()
            || ! $nurse->exists
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value
            || trim($reason) === '') {
            throw new LogicException('Escalation requires an in-progress recovery and its responsible active Nurse.');
        }

        self::$writingFromWorkflow = true;

        try {
            $escalation = new self;
            $escalation->recoveryEpisode()->associate($recoveryEpisode);
            $escalation->escalatedBy()->associate($nurse);
            $escalation->reason = trim($reason);
            $escalation->status = RecoveryEscalationStatus::Open;
            $escalation->open_marker = true;
            $escalation->escalated_at = now();
            $escalation->save();

            return $escalation;
        } finally {
            self::$writingFromWorkflow = false;
        }
    }

    public function resolveFromClinicalWorkflow(
        User $doctor,
        RecoveryEscalationResolution $resolution,
        ?string $resolutionNote,
    ): void {
        if (! $this->exists
            || $this->status !== RecoveryEscalationStatus::Open
            || ! $doctor->exists
            || ! $doctor->is_active
            || $doctor->role?->slug !== StaffRole::Doctor->value) {
            throw new LogicException('Only an active Doctor may resolve an open recovery escalation.');
        }

        self::$writingFromWorkflow = true;

        try {
            $this->resolvedBy()->associate($doctor);
            $this->resolution = $resolution;
            $this->resolution_note = $resolutionNote;
            $this->resolved_at = now();
            $this->status = RecoveryEscalationStatus::Resolved;
            $this->open_marker = null;
            $this->save();
        } finally {
            self::$writingFromWorkflow = false;
        }
    }

    protected static function booted(): void
    {
        static::saving(function (): void {
            if (! self::$writingFromWorkflow) {
                throw new LogicException('Recovery escalations may only change through their authoritative workflow actions.');
            }
        });

        static::deleting(fn () => throw new LogicException('Recovery escalations cannot be deleted.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RecoveryEscalationStatus::class,
            'open_marker' => 'boolean',
            'escalated_at' => 'immutable_datetime',
            'resolution' => RecoveryEscalationResolution::class,
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
