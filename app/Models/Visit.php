<?php

namespace App\Models;

use App\VisitStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $appointment_id
 * @property string $visit_number
 * @property CarbonImmutable $occurred_at
 * @property VisitStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Appointment|null $appointment
 * @property-read Collection<int, Bill> $bills
 * @property-read Patient $patient
 */
#[Fillable(['patient_id', 'occurred_at'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => VisitStatus::Created->value,
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Visit $visit): void {
            $visit->visit_number = 'TMP-'.Str::ulid();
            $visit->status = VisitStatus::Created;
        });

        static::created(function (Visit $visit): void {
            $visit->visit_number = self::visitNumberFor((int) $visit->getKey());
            $visit->saveQuietly();
        });

        static::updating(function (Visit $visit): void {
            if ($visit->isDirty('visit_number')) {
                throw new LogicException('Visit numbers cannot be changed.');
            }

            if ($visit->isDirty('appointment_id')) {
                throw new LogicException('A Visit appointment linkage cannot be changed.');
            }
        });
    }

    private static function visitNumberFor(int $id): string
    {
        return sprintf('VIS-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'status' => VisitStatus::class,
        ];
    }
}
