<?php

namespace App\Actions\Visits;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\Consultation;
use App\Models\ProcedureDecision;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\User;
use App\Models\Visit;
use App\ProcedureDecisionOutcome;
use App\RecoveryEpisodeStatus;
use App\VisitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteVisit
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function afterRecoveryDischarge(User $actor, RecoveryDischarge $recoveryDischarge): Visit
    {
        return DB::transaction(function () use ($actor, $recoveryDischarge): Visit {
            $lockedDischarge = RecoveryDischarge::query()
                ->whereKey($recoveryDischarge->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedDischarge instanceof RecoveryDischarge
                || $lockedDischarge->discharged_by_user_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'visit' => 'Visit completion requires the authoritative Nursing discharge handoff.',
                ]);
            }

            $lockedRecovery = RecoveryEpisode::query()
                ->whereKey($lockedDischarge->recovery_episode_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedRecovery instanceof RecoveryEpisode
                || $lockedRecovery->getRawOriginal('status') !== RecoveryEpisodeStatus::Completed->value
                || $lockedRecovery->completed_at === null
                || ! $lockedRecovery->completed_at->equalTo($lockedDischarge->discharged_at)) {
                throw ValidationException::withMessages([
                    'visit' => 'Visit completion requires a finalized completed Recovery handoff.',
                ]);
            }

            return $this->complete(
                actor: $actor,
                authoritativeHandoff: $lockedDischarge,
                visitId: $lockedRecovery->visit_id,
                completedAt: $lockedDischarge->discharged_at,
                sourceType: 'recovery_discharge',
                sourceId: $lockedDischarge->id,
                sourceReference: $lockedDischarge->discharge_number,
            );
        }, attempts: 3);
    }

    public function afterNoProcedureDecision(User $actor, ProcedureDecision $procedureDecision): Visit
    {
        return DB::transaction(function () use ($actor, $procedureDecision): Visit {
            $lockedDecision = ProcedureDecision::query()
                ->whereKey($procedureDecision->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedDecision instanceof ProcedureDecision
                || $lockedDecision->outcome !== ProcedureDecisionOutcome::NoProcedure
                || $lockedDecision->doctor_user_id !== $actor->getKey()) {
                throw ValidationException::withMessages([
                    'visit' => 'Visit completion requires the authoritative no-procedure Doctor decision.',
                ]);
            }

            return $this->complete(
                actor: $actor,
                authoritativeHandoff: $lockedDecision,
                visitId: $lockedDecision->visit_id,
                completedAt: $lockedDecision->decided_at,
                sourceType: 'no_procedure_decision',
                sourceId: $lockedDecision->id,
                sourceReference: $lockedDecision->decision_number,
                consultationId: $lockedDecision->consultation_id,
            );
        }, attempts: 3);
    }

    private function complete(
        User $actor,
        RecoveryDischarge|ProcedureDecision $authoritativeHandoff,
        int $visitId,
        CarbonImmutable $completedAt,
        string $sourceType,
        int $sourceId,
        string $sourceReference,
        ?int $consultationId = null,
    ): Visit {
        $visit = Visit::query()->whereKey($visitId)->lockForUpdate()->first();

        if (! $visit instanceof Visit) {
            throw ValidationException::withMessages(['visit' => 'The Visit completion target is unavailable.']);
        }

        if ($visit->status === VisitStatus::Completed) {
            return $visit;
        }

        if ($visit->status !== VisitStatus::CheckedIn || $visit->completed_at !== null) {
            throw ValidationException::withMessages([
                'visit' => 'Only an open checked-in Visit may be completed.',
            ]);
        }

        $consultationQuery = Consultation::query()->where('visit_id', $visit->getKey());

        if ($consultationId !== null) {
            $consultationQuery->whereKey($consultationId);
        }

        $consultation = $consultationQuery->lockForUpdate()->first();

        if (! $consultation instanceof Consultation
            || $consultation->status !== ConsultationStatus::InProgress) {
            throw ValidationException::withMessages([
                'visit' => 'Visit completion requires its open authoritative Consultation.',
            ]);
        }

        $consultation->finalizeFromVisitCompletionWorkflow($visit, $completedAt);
        $visit->completeFromClinicalWorkflow($authoritativeHandoff, $completedAt);

        $this->recordAuditLog->handle(
            actor: $actor,
            action: AuditAction::VisitCompleted,
            subject: $visit,
            afterValues: [
                'visit_id' => $visit->id,
                'visit_number' => $visit->visit_number,
                'patient_id' => $visit->patient_id,
                'completion_source_type' => $sourceType,
                'completion_source_id' => $sourceId,
                'completion_source_reference' => $sourceReference,
                'completed_at' => $visit->completed_at?->toIso8601String(),
            ],
        );

        return $visit->refresh();
    }
}
