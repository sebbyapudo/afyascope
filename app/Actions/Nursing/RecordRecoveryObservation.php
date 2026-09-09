<?php

namespace App\Actions\Nursing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryObservation;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordRecoveryObservation
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, RecoveryEpisode $recoveryEpisode, array $attributes): RecoveryObservation
    {
        Gate::forUser($actor)->authorize('update', $recoveryEpisode);
        $validated = $this->validatedAttributes($attributes);

        return DB::transaction(function () use ($actor, $recoveryEpisode, $validated): RecoveryObservation {
            $lockedRecovery = RecoveryEpisode::query()->lockForUpdate()->find($recoveryEpisode->getKey());

            if (! $lockedRecovery instanceof RecoveryEpisode
                || $lockedRecovery->getRawOriginal('status') !== RecoveryEpisodeStatus::InProgress->value
                || $lockedRecovery->nurse_user_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'recovery' => 'Observations require your active in-progress recovery episode.',
                ]);
            }

            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', fn (Builder $query) => $query->where('slug', StaffRole::Nurse->value))
                ->lockForUpdate()
                ->first();

            if (! $lockedActor instanceof User) {
                throw ValidationException::withMessages([
                    'actor' => 'Recording observations requires the responsible active Nurse.',
                ]);
            }

            $observation = RecoveryObservation::recordFromNursingWorkflow($lockedRecovery, $lockedActor, $validated);

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::RecoveryObservationRecorded,
                subject: $observation,
                afterValues: [
                    'recovery_observation_id' => $observation->getKey(),
                    'recovery_episode_id' => $lockedRecovery->getKey(),
                    'recovery_number' => $lockedRecovery->recovery_number,
                    'visit_id' => $lockedRecovery->visit_id,
                    'nurse_user_id' => $lockedActor->getKey(),
                    'recorded_at' => $observation->recorded_at->toIso8601String(),
                ],
            );

            return $observation->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{general_recovery_status: string, pain_score: int|null, nausea: bool, vomiting: bool, systolic_blood_pressure: int|null, diastolic_blood_pressure: int|null, pulse_rate: int|null, respiratory_rate: int|null, oxygen_saturation: int|null, supplemental_oxygen: bool, nursing_note: string|null}
     */
    private function validatedAttributes(array $attributes): array
    {
        $attributes = array_replace([
            'pain_score' => null,
            'systolic_blood_pressure' => null,
            'diastolic_blood_pressure' => null,
            'pulse_rate' => null,
            'respiratory_rate' => null,
            'oxygen_saturation' => null,
            'nursing_note' => null,
        ], $attributes);

        if (is_string($attributes['general_recovery_status'] ?? null)) {
            $attributes['general_recovery_status'] = trim($attributes['general_recovery_status']);
        }

        if (is_string($attributes['nursing_note'])) {
            $attributes['nursing_note'] = trim($attributes['nursing_note']) ?: null;
        }

        /** @var array{general_recovery_status: string, pain_score: int|null, nausea: bool, vomiting: bool, systolic_blood_pressure: int|null, diastolic_blood_pressure: int|null, pulse_rate: int|null, respiratory_rate: int|null, oxygen_saturation: int|null, supplemental_oxygen: bool, nursing_note: string|null} $validated */
        $validated = Validator::make($attributes, self::rules())->validate();

        return $validated;
    }

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'general_recovery_status' => ['required', 'string', 'max:255'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'nausea' => ['required', 'boolean'],
            'vomiting' => ['required', 'boolean'],
            'systolic_blood_pressure' => ['nullable', 'integer', 'between:30,300', 'required_with:diastolic_blood_pressure'],
            'diastolic_blood_pressure' => ['nullable', 'integer', 'between:20,200', 'required_with:systolic_blood_pressure'],
            'pulse_rate' => ['nullable', 'integer', 'between:20,300'],
            'respiratory_rate' => ['nullable', 'integer', 'between:4,100'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:0,100'],
            'supplemental_oxygen' => ['required', 'boolean'],
            'nursing_note' => ['nullable', 'string', 'max:5000'],
            'id' => ['prohibited'],
            'recovery_episode_id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'procedure_record_id' => ['prohibited'],
            'recorded_by_user_id' => ['prohibited'],
            'recorded_at' => ['prohibited'],
            'status' => ['prohibited'],
            'completed_at' => ['prohibited'],
        ];
    }
}
