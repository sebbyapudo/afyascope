<?php

namespace App\Models;

use App\AuditAction;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property AuditAction $action
 * @property string $subject_type
 * @property int $subject_id
 * @property array<string, mixed>|null $before_values
 * @property array<string, mixed>|null $after_values
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property-read User|null $actor
 * @property-read Model|null $subject
 */
#[Guarded(['*'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'before_values' => 'array',
            'after_values' => 'array',
            'metadata' => 'array',
        ];
    }
}
