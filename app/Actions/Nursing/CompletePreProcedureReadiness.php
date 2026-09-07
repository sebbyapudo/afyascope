<?php

namespace App\Actions\Nursing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\Models\Visit;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompletePreProcedureReadiness
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(
        User $actor,
        PreProcedureReadiness $preProcedureReadiness,
    ): PreProcedureReadiness {
        Gate::forUser($actor)->authorize('complete', $preProcedureReadiness);

        return DB::transaction(function () use ($actor, $preProcedureReadiness): PreProcedureReadiness {
            $lockedReadiness = PreProcedureReadiness::query()
                ->lockForUpdate()
                ->find($preProcedureReadiness->getKey());

            if (! $lockedReadiness instanceof PreProcedureReadiness
                || $lockedReadiness->getRawOriginal('status') !== PreProcedureReadinessStatus::InPreparation->value) {
                throw ValidationException::withMessages([
                    'readiness' => 'This preparation is not available for completion.',
                ]);
            }

            $lockedVisit = Visit::query()->lockForUpdate()->find($lockedReadiness->visit_id);
            $lockedDecision = ProcedureDecision::query()
                ->lockForUpdate()
                ->find($lockedReadiness->procedure_decision_id);
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Nurse->value);
                })
                ->lockForUpdate()
                ->first();

            if (! $lockedActor instanceof User
                || $lockedReadiness->nurse_user_id !== $lockedActor->getKey()
                || ! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::CheckedIn->value
                || ! $lockedDecision instanceof ProcedureDecision
                || $lockedDecision->visit_id !== $lockedVisit->getKey()
                || $lockedDecision->outcome !== ProcedureDecisionOutcome::ProcedureRequired) {
                throw ValidationException::withMessages([
                    'readiness' => 'Only the responsible active Nurse may complete this procedure preparation.',
                ]);
            }

            if (! $lockedReadiness->hasAllMandatoryChecks()) {
                throw ValidationException::withMessages([
                    'readiness' => 'Verify consent, patient identity, procedure, allergies, and medications before declaring readiness.',
                ]);
            }

            $lockedReadiness->completeFromNursingWorkflow($lockedActor);

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::NursingReadinessCompleted,
                subject: $lockedReadiness,
                afterValues: [
                    'readiness_number' => $lockedReadiness->readiness_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'procedure_decision_id' => $lockedDecision->getKey(),
                    'nurse_user_id' => $lockedActor->getKey(),
                    'status' => $lockedReadiness->status->value,
                ],
            );

            return $lockedReadiness->refresh();
        }, attempts: 3);
    }
}
