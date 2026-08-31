<?php

namespace App\Models;

use App\AppointmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property int $patient_id
 * @property string $appointment_number
 * @property CarbonImmutable $scheduled_at
 * @property AppointmentStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Patient $patient
 */
#[Fillable(['patient_id', 'scheduled_at'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => AppointmentStatus::Scheduled->value,
    ];

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            $appointment->appointment_number = 'TMP-'.Str::ulid();
            $appointment->status = AppointmentStatus::Scheduled;
        });

        static::created(function (Appointment $appointment): void {
            $appointment->appointment_number = self::appointmentNumberFor((int) $appointment->getKey());
            $appointment->saveQuietly();
        });

        static::updating(function (Appointment $appointment): void {
            if ($appointment->isDirty('appointment_number')) {
                throw new LogicException('Appointment numbers cannot be changed.');
            }

            if ($appointment->isDirty('patient_id')) {
                throw new LogicException('Appointments cannot be reassigned to another Patient.');
            }
        });
    }

    private static function appointmentNumberFor(int $id): string
    {
        return sprintf('APT-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
        ];
    }
}
