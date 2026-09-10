<?php

namespace App\Actions\Clinical;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\RecoveryEscalationResolution;
use App\RecoveryEscalationStatus;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResolveRecoveryEscalation
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /** @param array<string, mixed> $attributes */
    public function handle(
        User $actor,
        RecoveryEscalation $recoveryEscalation,
        array $attributes,
    ): RecoveryEscalation {
        Gate::forUser($actor)->authorize('resolve', $recoveryEscalation);
        $validated = $this->validatedAttributes($attributes);

        return DB::transaction(function () use ($actor, $recoveryEscalation, $validated): RecoveryEscalation {
            $lockedEscalation = RecoveryEscalation::query()
                ->lockForUpdate()
                ->find($recoveryEscalation->getKey());

            if (! $lockedEscalation instanceof RecoveryEscalation
                || $lockedEscalation->getRawOriginal('status') !== RecoveryEscalationStatus::Open->value
                || $lockedEscalation->open_marker !== true) {
                throw ValidationException::withMessages([
                    'escalation' => 'This recovery escalation has already been resolved.',
                ]);
            }

            $lockedRecovery = RecoveryEpisode::query()
                ->lockForUpdate()
                ->find($lockedEscalation->recovery_episode_id);

            if (! $lockedRecovery instanceof RecoveryEpisode
                || $lockedRecovery->getRawOriginal('status') !== RecoveryEpisodeStatus::InProgress->value) {
                throw ValidationException::withMessages([
                    'recovery' => 'Only an in-progress recovery escalation may be resolved.',
                ]);
            }

            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', fn (Builder $query) => $query->where('slug', StaffRole::Doctor->value))
                ->lockForUpdate()
                ->first();

            if (! $lockedActor instanceof User) {
                throw ValidationException::withMessages([
                    'actor' => 'Resolving recovery escalation requires an active Doctor.',
                ]);
            }

            $resolution = RecoveryEscalationResolution::from($validated['resolution']);
            $lockedEscalation->resolveFromClinicalWorkflow(
                $lockedActor,
                $resolution,
                $validated['resolution_note'],
            );

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::RecoveryEscalationResolved,
                subject: $lockedEscalation,
                afterValues: [
                    'recovery_escalation_id' => $lockedEscalation->getKey(),
                    'recovery_episode_id' => $lockedRecovery->getKey(),
                    'recovery_number' => $lockedRecovery->recovery_number,
                    'visit_id' => $lockedRecovery->visit_id,
                    'resolved_by_user_id' => $lockedActor->getKey(),
                    'resolution' => $resolution->value,
                    'status' => $lockedEscalation->status->value,
                    'resolved_at' => $lockedEscalation->resolved_at?->toIso8601String(),
                ],
            );

            return $lockedEscalation->refresh();
        }, attempts: 3);
    }

    /** @return array<string, list<string|Rule>> */
    public static function rules(): array
    {
        return [
            'resolution' => ['required', Rule::enum(RecoveryEscalationResolution::class)],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
            'id' => ['prohibited'],
            'recovery_episode_id' => ['prohibited'],
            'resolved_by_user_id' => ['prohibited'],
            'resolved_at' => ['prohibited'],
            'status' => ['prohibited'],
            'open_marker' => ['prohibited'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{resolution: string, resolution_note: string|null}
     */
    private function validatedAttributes(array $attributes): array
    {
        $attributes = array_replace(['resolution_note' => null], $attributes);

        if (is_string($attributes['resolution_note'])) {
            $attributes['resolution_note'] = trim($attributes['resolution_note']) ?: null;
        }

        /** @var array{resolution: string, resolution_note: string|null} $validated */
        $validated = Validator::make($attributes, self::rules())->validate();

        return $validated;
    }
}
