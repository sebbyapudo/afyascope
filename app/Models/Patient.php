<?php

namespace App\Models;

use App\PatientSex;
use Carbon\CarbonImmutable;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property int $id
 * @property string $patient_number
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property CarbonImmutable|null $date_of_birth
 * @property PatientSex|null $sex
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Visit> $visits
 */
#[Fillable([
    'first_name',
    'middle_name',
    'last_name',
    'date_of_birth',
    'sex',
    'phone',
    'email',
    'address',
])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Patient $patient): void {
            $patient->patient_number = 'TMP-'.Str::ulid();
        });

        static::created(function (Patient $patient): void {
            $patient->patient_number = self::patientNumberFor((int) $patient->getKey());
            $patient->saveQuietly();
        });

        static::updating(function (Patient $patient): void {
            if ($patient->isDirty('patient_number')) {
                throw new LogicException('Patient numbers cannot be changed.');
            }
        });
    }

    private static function patientNumberFor(int $id): string
    {
        return sprintf('PAT-%06d', $id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'sex' => PatientSex::class,
        ];
    }
}
