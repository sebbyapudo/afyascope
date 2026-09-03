<?php

namespace App\Models;

use App\AsaClassification;
use App\ConsultationStatus;
use App\StaffRole;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ConsultationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property int $doctor_user_id
 * @property string $consultation_number
 * @property ConsultationStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finalized_at
 * @property string|null $presenting_complaint
 * @property string|null $relevant_history
 * @property string|null $current_medications
 * @property string|null $allergies
 * @property string|null $examination_findings
 * @property AsaClassification|null $asa_classification
 * @property string|null $assessment_impression
 * @property string|null $plan_notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Visit $visit
 * @property-read User $doctor
 * @property-read ProcedureDecision|null $procedureDecision
 */
#[Fillable(['visit_id', 'doctor_user_id'])]
class Consultation extends Model
{
    /** @use HasFactory<ConsultationFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => ConsultationStatus::InProgress->value,
    ];

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    /** @return HasOne<ProcedureDecision, $this> */
    public function procedureDecision(): HasOne
    {
        return $this->hasOne(ProcedureDecision::class);
    }

    public function isFinalized(): bool
    {
        return $this->status === ConsultationStatus::Finalized;
    }

    protected static function booted(): void
    {
        static::creating(function (Consultation $consultation): void {
            $visit = Visit::query()
                ->with('checkIn:id,visit_id')
                ->find($consultation->visit_id);

            if (! $visit instanceof Visit
                || $visit->status !== VisitStatus::CheckedIn
                || ! $visit->checkIn instanceof VisitCheckIn) {
                throw new LogicException('A Consultation requires a checked-in Visit.');
            }

            $isActiveDoctor = User::query()
                ->whereKey($consultation->doctor_user_id)
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Doctor->value);
                })
                ->exists();

            if (! $isActiveDoctor) {
                throw new LogicException('A Consultation requires an active Doctor.');
            }

            $consultation->consultation_number = 'TMP-'.Str::ulid();
            $consultation->status = ConsultationStatus::InProgress;
            $consultation->started_at = now();
            $consultation->finalized_at = null;
        });

        static::created(function (Consultation $consultation): void {
            $consultation->consultation_number = self::consultationNumberFor((int) $consultation->getKey());
            $consultation->saveQuietly();
        });

        static::updating(function (Consultation $consultation): void {
            if ($consultation->getRawOriginal('status') === ConsultationStatus::Finalized->value
                && $consultation->isDirty()) {
                throw new LogicException('Finalized Consultations cannot be changed.');
            }

            if ($consultation->isDirty([
                'visit_id',
                'doctor_user_id',
                'consultation_number',
                'started_at',
            ])) {
                throw new LogicException('Consultation ownership and server-controlled fields cannot be changed.');
            }

            if ($consultation->isDirty(['status', 'finalized_at'])) {
                throw new LogicException('Consultation lifecycle changes require their authoritative workflow action.');
            }
        });
    }

    private static function consultationNumberFor(int $id): string
    {
        return sprintf('CON-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConsultationStatus::class,
            'asa_classification' => AsaClassification::class,
            'started_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime',
        ];
    }
}
