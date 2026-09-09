<?php

namespace App\Models;

use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Carbon\CarbonImmutable;
use Database\Factories\RecoveryObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $recovery_episode_id
 * @property int $recorded_by_user_id
 * @property string $general_recovery_status
 * @property int|null $pain_score
 * @property bool $nausea
 * @property bool $vomiting
 * @property int|null $systolic_blood_pressure
 * @property int|null $diastolic_blood_pressure
 * @property int|null $pulse_rate
 * @property int|null $respiratory_rate
 * @property int|null $oxygen_saturation
 * @property bool $supplemental_oxygen
 * @property string|null $nursing_note
 * @property CarbonImmutable $recorded_at
 * @property-read RecoveryEpisode $recoveryEpisode
 * @property-read User $recordedBy
 */
#[Guarded(['*'])]
class RecoveryObservation extends Model
{
    /** @use HasFactory<RecoveryObservationFactory> */
    use HasFactory;

    private static bool $recordingFromNursingWorkflow = false;

    /** @return BelongsTo<RecoveryEpisode, $this> */
    public function recoveryEpisode(): BelongsTo
    {
        return $this->belongsTo(RecoveryEpisode::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @param  array{general_recovery_status: string, pain_score: int|null, nausea: bool, vomiting: bool, systolic_blood_pressure: int|null, diastolic_blood_pressure: int|null, pulse_rate: int|null, respiratory_rate: int|null, oxygen_saturation: int|null, supplemental_oxygen: bool, nursing_note: string|null}  $attributes
     */
    public static function recordFromNursingWorkflow(RecoveryEpisode $recoveryEpisode, User $nurse, array $attributes): self
    {
        if (! $recoveryEpisode->exists
            || $recoveryEpisode->status !== RecoveryEpisodeStatus::InProgress
            || $recoveryEpisode->nurse_user_id !== $nurse->getKey()
            || ! $nurse->exists
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value) {
            throw new LogicException('A recovery observation requires its in-progress episode and responsible active Nurse.');
        }

        self::$recordingFromNursingWorkflow = true;

        try {
            $observation = new self;
            $observation->recoveryEpisode()->associate($recoveryEpisode);
            $observation->recordedBy()->associate($nurse);
            $observation->general_recovery_status = $attributes['general_recovery_status'];
            $observation->pain_score = $attributes['pain_score'];
            $observation->nausea = $attributes['nausea'];
            $observation->vomiting = $attributes['vomiting'];
            $observation->systolic_blood_pressure = $attributes['systolic_blood_pressure'];
            $observation->diastolic_blood_pressure = $attributes['diastolic_blood_pressure'];
            $observation->pulse_rate = $attributes['pulse_rate'];
            $observation->respiratory_rate = $attributes['respiratory_rate'];
            $observation->oxygen_saturation = $attributes['oxygen_saturation'];
            $observation->supplemental_oxygen = $attributes['supplemental_oxygen'];
            $observation->nursing_note = $attributes['nursing_note'];
            $observation->save();

            return $observation;
        } finally {
            self::$recordingFromNursingWorkflow = false;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (RecoveryObservation $observation): void {
            if (! self::$recordingFromNursingWorkflow) {
                throw new LogicException('Recovery observations may only be recorded through the authoritative Nursing workflow.');
            }

            $observation->recorded_at = now();
        });

        static::updating(fn () => throw new LogicException('Recovery observations are append-only.'));
        static::deleting(fn () => throw new LogicException('Recovery observations cannot be deleted.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pain_score' => 'integer',
            'nausea' => 'boolean',
            'vomiting' => 'boolean',
            'systolic_blood_pressure' => 'integer',
            'diastolic_blood_pressure' => 'integer',
            'pulse_rate' => 'integer',
            'respiratory_rate' => 'integer',
            'oxygen_saturation' => 'integer',
            'supplemental_oxygen' => 'boolean',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
