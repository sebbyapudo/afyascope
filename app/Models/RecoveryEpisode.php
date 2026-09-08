<?php

namespace App\Models;

use App\RecoveryEpisodeStatus;
use Carbon\CarbonImmutable;
use Database\Factories\RecoveryEpisodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 */
class RecoveryEpisode extends Model
{
    /** @use HasFactory<RecoveryEpisodeFactory> */
    use HasFactory;

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

    protected static function booted(): void
    {
        static::creating(function (): void {
            throw new LogicException(
                'Recovery episodes may only begin through the future authoritative Nursing workflow.',
            );
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
