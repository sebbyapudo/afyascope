<?php

namespace App\Actions\Nursing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\RecoveryReadinessAssessment;
use App\Models\User;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssessRecoveryReadiness
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        User $actor,
        RecoveryEpisode $recoveryEpisode,
        array $attributes,
    ): RecoveryReadinessAssessment {
        Gate::forUser($actor)->authorize('assessReadiness', $recoveryEpisode);
        $validated = $this->validatedAttributes($attributes);

        return DB::transaction(function () use ($actor, $recoveryEpisode, $validated): RecoveryReadinessAssessment {
            $lockedRecovery = RecoveryEpisode::query()
                ->lockForUpdate()
                ->find($recoveryEpisode->getKey());

            if (! $lockedRecovery instanceof RecoveryEpisode
                || $lockedRecovery->getRawOriginal('status') !== RecoveryEpisodeStatus::InProgress->value
                || $lockedRecovery->nurse_user_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'recovery' => 'Readiness assessment requires your active in-progress recovery episode.',
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
                    'actor' => 'Assessing recovery readiness requires the responsible active Nurse.',
                ]);
            }

            $openEscalation = RecoveryEscalation::query()
                ->where('recovery_episode_id', $lockedRecovery->getKey())
                ->where('open_marker', true)
                ->lockForUpdate()
                ->first();

            if ($openEscalation instanceof RecoveryEscalation) {
                throw ValidationException::withMessages([
                    'recovery' => 'Doctor review is required before readiness can be reassessed.',
                ]);
            }

            $currentAssessment = RecoveryReadinessAssessment::query()
                ->where('recovery_episode_id', $lockedRecovery->getKey())
                ->lockForUpdate()
                ->first();

            $assessment = RecoveryReadinessAssessment::recordFromNursingWorkflow(
                $lockedRecovery,
                $lockedActor,
                [
                    'criteria_met' => $validated['criteria_met'],
                    'clinical_concern_requires_escalation' => $validated['clinical_concern_requires_escalation'],
                    'assessment_note' => $validated['assessment_note'],
                ],
                $currentAssessment,
            );

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::RecoveryReadinessAssessed,
                subject: $assessment,
                afterValues: [
                    'recovery_readiness_assessment_id' => $assessment->getKey(),
                    'recovery_episode_id' => $lockedRecovery->getKey(),
                    'recovery_number' => $lockedRecovery->recovery_number,
                    'visit_id' => $lockedRecovery->visit_id,
                    'criteria_met' => $assessment->criteria_met,
                    'clinical_concern_requires_escalation' => $assessment->clinical_concern_requires_escalation,
                    'assessed_by_user_id' => $lockedActor->getKey(),
                    'assessed_at' => $assessment->assessed_at->toIso8601String(),
                ],
            );

            if ($validated['clinical_concern_requires_escalation']) {
                $escalation = RecoveryEscalation::escalateFromNursingWorkflow(
                    $lockedRecovery,
                    $lockedActor,
                    $validated['escalation_reason'],
                );

                $this->recordAuditLog->handle(
                    actor: $lockedActor,
                    action: AuditAction::RecoveryEscalated,
                    subject: $escalation,
                    afterValues: [
                        'recovery_escalation_id' => $escalation->getKey(),
                        'recovery_episode_id' => $lockedRecovery->getKey(),
                        'recovery_number' => $lockedRecovery->recovery_number,
                        'visit_id' => $lockedRecovery->visit_id,
                        'escalated_by_user_id' => $lockedActor->getKey(),
                        'status' => $escalation->status->value,
                        'escalated_at' => $escalation->escalated_at->toIso8601String(),
                    ],
                );
            } elseif ($validated['criteria_met']) {
                $lockedRecovery->markReadyForDischargeFromNursingWorkflow($lockedActor);
            }

            return $assessment->refresh();
        }, attempts: 3);
    }

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'criteria_met' => ['required', 'boolean'],
            'clinical_concern_requires_escalation' => ['required', 'boolean'],
            'assessment_note' => ['nullable', 'string', 'max:1000'],
            'escalation_reason' => ['nullable', 'required_if:clinical_concern_requires_escalation,true', 'string', 'max:1000'],
            'id' => ['prohibited'],
            'recovery_episode_id' => ['prohibited'],
            'assessed_by_user_id' => ['prohibited'],
            'assessed_at' => ['prohibited'],
            'status' => ['prohibited'],
            'open_marker' => ['prohibited'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{criteria_met: bool, clinical_concern_requires_escalation: bool, assessment_note: string|null, escalation_reason: string|null}
     */
    private function validatedAttributes(array $attributes): array
    {
        $attributes = array_replace([
            'assessment_note' => null,
            'escalation_reason' => null,
        ], $attributes);

        foreach (['assessment_note', 'escalation_reason'] as $field) {
            if (is_string($attributes[$field])) {
                $attributes[$field] = trim($attributes[$field]) ?: null;
            }
        }

        $validator = Validator::make($attributes, self::rules());
        $validator->after(function ($validator) use ($attributes): void {
            if (filter_var($attributes['criteria_met'] ?? false, FILTER_VALIDATE_BOOL)
                && filter_var($attributes['clinical_concern_requires_escalation'] ?? false, FILTER_VALIDATE_BOOL)) {
                $validator->errors()->add(
                    'criteria_met',
                    'Recovery criteria cannot be met while a clinical concern requires escalation.',
                );
            }
        });

        /** @var array{criteria_met: bool, clinical_concern_requires_escalation: bool, assessment_note: string|null, escalation_reason: string|null} $validated */
        $validated = $validator->validate();

        return $validated;
    }
}
