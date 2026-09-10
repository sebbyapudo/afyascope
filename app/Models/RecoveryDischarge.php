<?php

namespace App\Models;

use App\RecoveryDischargeAccompanimentStatus;
use App\RecoveryDischargeDisposition;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Database\Factories\RecoveryDischargeFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $recovery_episode_id
 * @property int $discharged_by_user_id
 * @property string $discharge_number
 * @property string $condition_summary
 * @property RecoveryDischargeAccompanimentStatus $accompaniment_status
 * @property RecoveryDischargeDisposition $disposition
 * @property string|null $nursing_note
 * @property string $general_care_instructions
 * @property string $activity_driving_instructions
 * @property string $diet_fluids_instructions
 * @property string|null $medication_instructions
 * @property string $warning_signs_instructions
 * @property string|null $follow_up_instructions
 * @property CarbonImmutable $discharged_at
 * @property-read RecoveryEpisode $recoveryEpisode
 * @property-read User $dischargedBy
 */
#[Guarded(['*'])]
class RecoveryDischarge extends Model
{
    /** @use HasFactory<RecoveryDischargeFactory> */
    use HasFactory;

    private static bool $finalizingFromNursingWorkflow = false;

    /** @return BelongsTo<RecoveryEpisode, $this> */
    public function recoveryEpisode(): BelongsTo
    {
        return $this->belongsTo(RecoveryEpisode::class);
    }

    /** @return BelongsTo<User, $this> */
    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by_user_id');
    }

    /**
     * @param  array{condition_summary: string, accompaniment_status: RecoveryDischargeAccompanimentStatus, disposition: RecoveryDischargeDisposition, nursing_note: string|null, general_care_instructions: string, activity_driving_instructions: string, diet_fluids_instructions: string, medication_instructions: string|null, warning_signs_instructions: string, follow_up_instructions: string|null}  $attributes
     */
    public static function finalizeFromNursingWorkflow(
        RecoveryEpisode $recoveryEpisode,
        User $nurse,
        array $attributes,
    ): self {
        $assessment = $recoveryEpisode->readinessAssessment()->first();

        if (! $recoveryEpisode->exists
            || $recoveryEpisode->status !== RecoveryEpisodeStatus::ReadyForDischarge
            || $recoveryEpisode->nurse_user_id !== $nurse->getKey()
            || ! $nurse->exists
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value
            || ! $assessment instanceof RecoveryReadinessAssessment
            || ! $assessment->criteria_met
            || $assessment->clinical_concern_requires_escalation
            || $recoveryEpisode->openEscalation()->exists()
            || $recoveryEpisode->discharge()->exists()) {
            throw new LogicException('Discharge requires an uncomplicated ready recovery and its responsible active Nurse.');
        }

        self::$finalizingFromNursingWorkflow = true;

        try {
            $discharge = new self;
            $discharge->recoveryEpisode()->associate($recoveryEpisode);
            $discharge->dischargedBy()->associate($nurse);
            $discharge->condition_summary = $attributes['condition_summary'];
            $discharge->accompaniment_status = $attributes['accompaniment_status'];
            $discharge->disposition = $attributes['disposition'];
            $discharge->nursing_note = $attributes['nursing_note'];
            $discharge->general_care_instructions = $attributes['general_care_instructions'];
            $discharge->activity_driving_instructions = $attributes['activity_driving_instructions'];
            $discharge->diet_fluids_instructions = $attributes['diet_fluids_instructions'];
            $discharge->medication_instructions = $attributes['medication_instructions'];
            $discharge->warning_signs_instructions = $attributes['warning_signs_instructions'];
            $discharge->follow_up_instructions = $attributes['follow_up_instructions'];
            $discharge->save();

            return $discharge;
        } finally {
            self::$finalizingFromNursingWorkflow = false;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (RecoveryDischarge $discharge): void {
            if (! self::$finalizingFromNursingWorkflow) {
                throw new LogicException('Discharge records may only be finalized through the authoritative Nursing workflow.');
            }

            $discharge->discharge_number = 'TMP-'.Str::ulid();
            $discharge->discharged_at = now();
        });

        static::created(function (RecoveryDischarge $discharge): void {
            $discharge->discharge_number = sprintf('DSC-%06d', $discharge->id);
            $discharge->saveQuietly();
        });

        static::updating(fn () => throw new LogicException('Finalized discharge records cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Finalized discharge records cannot be deleted.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'accompaniment_status' => RecoveryDischargeAccompanimentStatus::class,
            'disposition' => RecoveryDischargeDisposition::class,
            'discharged_at' => 'immutable_datetime',
        ];
    }
}
