<?php

namespace App\Models;

use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Database\Factories\RecoveryReadinessAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $recovery_episode_id
 * @property int $assessed_by_user_id
 * @property bool $criteria_met
 * @property bool $clinical_concern_requires_escalation
 * @property string|null $assessment_note
 * @property CarbonImmutable $assessed_at
 * @property-read RecoveryEpisode $recoveryEpisode
 * @property-read User $assessedBy
 */
#[Guarded(['*'])]
class RecoveryReadinessAssessment extends Model
{
    /** @use HasFactory<RecoveryReadinessAssessmentFactory> */
    use HasFactory;

    private static bool $recordingFromNursingWorkflow = false;

    /** @return BelongsTo<RecoveryEpisode, $this> */
    public function recoveryEpisode(): BelongsTo
    {
        return $this->belongsTo(RecoveryEpisode::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }

    /**
     * @param  array{criteria_met: bool, clinical_concern_requires_escalation: bool, assessment_note: string|null}  $attributes
     */
    public static function recordFromNursingWorkflow(
        RecoveryEpisode $recoveryEpisode,
        User $nurse,
        array $attributes,
        ?self $currentAssessment = null,
    ): self {
        if (! $recoveryEpisode->exists
            || $recoveryEpisode->status !== RecoveryEpisodeStatus::InProgress
            || $recoveryEpisode->nurse_user_id !== $nurse->getKey()
            || ! $nurse->exists
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value
            || ($attributes['criteria_met'] && $attributes['clinical_concern_requires_escalation'])) {
            throw new LogicException('Readiness assessment requires a consistent decision by the responsible active Nurse.');
        }

        if ($currentAssessment instanceof self
            && $currentAssessment->recovery_episode_id !== $recoveryEpisode->getKey()) {
            throw new LogicException('A readiness assessment cannot move between recovery episodes.');
        }

        self::$recordingFromNursingWorkflow = true;

        try {
            $assessment = $currentAssessment ?? new self;
            $assessment->recoveryEpisode()->associate($recoveryEpisode);
            $assessment->assessedBy()->associate($nurse);
            $assessment->criteria_met = $attributes['criteria_met'];
            $assessment->clinical_concern_requires_escalation = $attributes['clinical_concern_requires_escalation'];
            $assessment->assessment_note = $attributes['assessment_note'];
            $assessment->assessed_at = now();
            $assessment->save();

            return $assessment;
        } finally {
            self::$recordingFromNursingWorkflow = false;
        }
    }

    protected static function booted(): void
    {
        static::saving(function (): void {
            if (! self::$recordingFromNursingWorkflow) {
                throw new LogicException('Recovery readiness may only be assessed through the authoritative Nursing workflow.');
            }
        });

        static::deleting(fn () => throw new LogicException('Recovery readiness assessments cannot be deleted.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'criteria_met' => 'boolean',
            'clinical_concern_requires_escalation' => 'boolean',
            'assessed_at' => 'immutable_datetime',
        ];
    }
}
