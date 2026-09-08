<?php

namespace Database\Factories;

use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LogicException;

/**
 * @extends Factory<RecoveryEpisode>
 */
class RecoveryEpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => RecoveryEpisodeStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function createAuthoritativeRecoveryFixture(
        ProcedureRecord $procedureRecord,
        User $nurse,
    ): RecoveryEpisode {
        if (! $procedureRecord->exists
            || ! $procedureRecord->isReadyForNursingRecovery()
            || ! $nurse->exists
            || ! $nurse->is_active
            || $nurse->role?->slug !== StaffRole::Nurse->value) {
            throw new LogicException(
                'A recovery fixture requires a completed Procedure Record and responsible active Nurse.',
            );
        }

        $recoveryEpisode = $this->makeOne();
        $recoveryEpisode->visit()->associate($procedureRecord->visit_id);
        $recoveryEpisode->procedureRecord()->associate($procedureRecord);
        $recoveryEpisode->nurse()->associate($nurse);
        $recoveryEpisode->recovery_number = 'TMP-'.Str::ulid();
        $recoveryEpisode->status ??= RecoveryEpisodeStatus::InProgress;
        $recoveryEpisode->started_at = now();
        $recoveryEpisode->completed_at = $recoveryEpisode->status === RecoveryEpisodeStatus::Completed
            ? ($recoveryEpisode->completed_at ?? now())
            : null;
        $recoveryEpisode->saveQuietly();
        $recoveryEpisode->recovery_number = sprintf('REC-%06d', $recoveryEpisode->id);
        $recoveryEpisode->saveQuietly();

        return $recoveryEpisode->refresh();
    }
}
