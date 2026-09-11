<?php

namespace App\Actions\Nursing;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Visits\CompleteVisit;
use App\AuditAction;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\RecoveryReadinessAssessment;
use App\Models\User;
use App\RecoveryDischargeAccompanimentStatus;
use App\RecoveryDischargeDisposition;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DischargeRecovery
{
    public function __construct(
        private RecordAuditLog $recordAuditLog,
        private CompleteVisit $completeVisit,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, RecoveryEpisode $recoveryEpisode, array $attributes): RecoveryDischarge
    {
        Gate::forUser($actor)->authorize('discharge', $recoveryEpisode);
        $validated = $this->validatedAttributes($attributes);

        return DB::transaction(function () use ($actor, $recoveryEpisode, $validated): RecoveryDischarge {
            $lockedRecovery = RecoveryEpisode::query()
                ->lockForUpdate()
                ->find($recoveryEpisode->getKey());

            if (! $lockedRecovery instanceof RecoveryEpisode
                || $lockedRecovery->getRawOriginal('status') !== RecoveryEpisodeStatus::ReadyForDischarge->value
                || $lockedRecovery->nurse_user_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'recovery' => 'Discharge requires your recovery episode to be ready for discharge.',
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
                    'actor' => 'Discharge requires the responsible active Nurse.',
                ]);
            }

            $readinessAssessment = RecoveryReadinessAssessment::query()
                ->where('recovery_episode_id', $lockedRecovery->getKey())
                ->lockForUpdate()
                ->first();

            if (! $readinessAssessment instanceof RecoveryReadinessAssessment
                || ! $readinessAssessment->criteria_met
                || $readinessAssessment->clinical_concern_requires_escalation) {
                throw ValidationException::withMessages([
                    'recovery' => 'The current Nursing readiness assessment does not permit discharge.',
                ]);
            }

            $openEscalation = RecoveryEscalation::query()
                ->where('recovery_episode_id', $lockedRecovery->getKey())
                ->where('open_marker', true)
                ->lockForUpdate()
                ->first();

            if ($openEscalation instanceof RecoveryEscalation) {
                throw ValidationException::withMessages([
                    'recovery' => 'Doctor review must be resolved before discharge.',
                ]);
            }

            $existingDischarge = RecoveryDischarge::query()
                ->where('recovery_episode_id', $lockedRecovery->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingDischarge instanceof RecoveryDischarge) {
                throw ValidationException::withMessages([
                    'recovery' => 'This recovery episode has already been discharged.',
                ]);
            }

            $discharge = RecoveryDischarge::finalizeFromNursingWorkflow(
                $lockedRecovery,
                $lockedActor,
                [
                    'condition_summary' => $validated['condition_summary'],
                    'accompaniment_status' => RecoveryDischargeAccompanimentStatus::from($validated['accompaniment_status']),
                    'disposition' => RecoveryDischargeDisposition::from($validated['disposition']),
                    'nursing_note' => $validated['nursing_note'],
                    'general_care_instructions' => $validated['general_care_instructions'],
                    'activity_driving_instructions' => $validated['activity_driving_instructions'],
                    'diet_fluids_instructions' => $validated['diet_fluids_instructions'],
                    'medication_instructions' => $validated['medication_instructions'],
                    'warning_signs_instructions' => $validated['warning_signs_instructions'],
                    'follow_up_instructions' => $validated['follow_up_instructions'],
                ],
            );

            $lockedRecovery->completeFromDischargeWorkflow($lockedActor, $discharge);

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::RecoveryDischarged,
                subject: $discharge,
                afterValues: [
                    'recovery_discharge_id' => $discharge->getKey(),
                    'discharge_number' => $discharge->discharge_number,
                    'recovery_episode_id' => $lockedRecovery->getKey(),
                    'recovery_number' => $lockedRecovery->recovery_number,
                    'visit_id' => $lockedRecovery->visit_id,
                    'discharged_by_user_id' => $lockedActor->getKey(),
                    'recovery_status' => $lockedRecovery->status->value,
                    'discharged_at' => $discharge->discharged_at->toIso8601String(),
                ],
            );

            $this->completeVisit->afterRecoveryDischarge($lockedActor, $discharge);

            return $discharge->refresh();
        }, attempts: 3);
    }

    /** @return array<string, list<string|Rule>> */
    public static function rules(): array
    {
        return [
            'condition_summary' => ['required', 'string', 'max:255'],
            'accompaniment_status' => ['required', Rule::enum(RecoveryDischargeAccompanimentStatus::class)],
            'disposition' => ['required', Rule::enum(RecoveryDischargeDisposition::class)],
            'nursing_note' => ['nullable', 'string', 'max:2000'],
            'general_care_instructions' => ['required', 'string', 'max:5000'],
            'activity_driving_instructions' => ['required', 'string', 'max:5000'],
            'diet_fluids_instructions' => ['required', 'string', 'max:5000'],
            'medication_instructions' => ['nullable', 'string', 'max:5000'],
            'warning_signs_instructions' => ['required', 'string', 'max:5000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:5000'],
            'confirm_discharge' => ['required', 'accepted'],
            'id' => ['prohibited'],
            'recovery_episode_id' => ['prohibited'],
            'visit_id' => ['prohibited'],
            'discharged_by_user_id' => ['prohibited'],
            'discharge_number' => ['prohibited'],
            'discharged_at' => ['prohibited'],
            'status' => ['prohibited'],
            'completed_at' => ['prohibited'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{condition_summary: string, accompaniment_status: string, disposition: string, nursing_note: string|null, general_care_instructions: string, activity_driving_instructions: string, diet_fluids_instructions: string, medication_instructions: string|null, warning_signs_instructions: string, follow_up_instructions: string|null, confirm_discharge: string|bool}
     */
    private function validatedAttributes(array $attributes): array
    {
        $attributes = array_replace([
            'nursing_note' => null,
            'medication_instructions' => null,
            'follow_up_instructions' => null,
        ], $attributes);

        foreach ([
            'condition_summary',
            'nursing_note',
            'general_care_instructions',
            'activity_driving_instructions',
            'diet_fluids_instructions',
            'medication_instructions',
            'warning_signs_instructions',
            'follow_up_instructions',
        ] as $field) {
            if (is_string($attributes[$field] ?? null)) {
                $attributes[$field] = trim($attributes[$field]) ?: null;
            }
        }

        /** @var array{condition_summary: string, accompaniment_status: string, disposition: string, nursing_note: string|null, general_care_instructions: string, activity_driving_instructions: string, diet_fluids_instructions: string, medication_instructions: string|null, warning_signs_instructions: string, follow_up_instructions: string|null, confirm_discharge: string|bool} $validated */
        $validated = Validator::make($attributes, self::rules())->validate();

        return $validated;
    }
}
