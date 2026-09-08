<?php

namespace App\Actions\Procedures;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\Consultation;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\Models\Visit;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompleteProcedureRecord
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(
        User $actor,
        ProcedureRecord $procedureRecord,
        int $expectedLockVersion,
    ): ProcedureRecord {
        Gate::forUser($actor)->authorize('complete', $procedureRecord);

        return DB::transaction(function () use (
            $actor,
            $procedureRecord,
            $expectedLockVersion,
        ): ProcedureRecord {
            $lockedProcedureRecord = ProcedureRecord::query()
                ->lockForUpdate()
                ->find($procedureRecord->getKey());

            if (! $lockedProcedureRecord instanceof ProcedureRecord
                || $lockedProcedureRecord->getRawOriginal('status') !== ProcedureRecordStatus::InProgress->value) {
                throw ValidationException::withMessages([
                    'procedure' => 'This procedure is not available for completion.',
                ]);
            }

            if ($lockedProcedureRecord->lock_version !== $expectedLockVersion) {
                throw ValidationException::withMessages([
                    'procedure' => 'This procedure record changed after you opened it. Reload before completing.',
                ]);
            }

            $lockedVisit = Visit::query()->lockForUpdate()->find($lockedProcedureRecord->visit_id);
            $lockedDecision = ProcedureDecision::query()
                ->lockForUpdate()
                ->find($lockedProcedureRecord->procedure_decision_id);
            $lockedReadiness = PreProcedureReadiness::query()
                ->lockForUpdate()
                ->find($lockedProcedureRecord->pre_procedure_readiness_id);
            $lockedConsultation = Consultation::query()
                ->lockForUpdate()
                ->find($lockedDecision?->consultation_id);
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Doctor->value);
                })
                ->lockForUpdate()
                ->first();

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::CheckedIn->value
                || ! $lockedDecision instanceof ProcedureDecision
                || $lockedDecision->visit_id !== $lockedVisit->getKey()
                || $lockedDecision->outcome !== ProcedureDecisionOutcome::ProcedureRequired
                || $lockedDecision->service_catalog_item_id !== $lockedProcedureRecord->service_catalog_item_id
                || ! $lockedReadiness instanceof PreProcedureReadiness
                || $lockedReadiness->visit_id !== $lockedVisit->getKey()
                || $lockedReadiness->procedure_decision_id !== $lockedDecision->getKey()
                || $lockedReadiness->status !== PreProcedureReadinessStatus::Ready
                || ! $lockedConsultation instanceof Consultation
                || $lockedConsultation->getRawOriginal('status') !== ConsultationStatus::InProgress->value
                || $lockedConsultation->doctor_user_id !== $lockedProcedureRecord->doctor_user_id
                || ! $lockedActor instanceof User
                || $lockedProcedureRecord->doctor_user_id !== $lockedActor->getKey()) {
                throw ValidationException::withMessages([
                    'procedure' => 'Only the responsible active Doctor may complete this ready procedure.',
                ]);
            }

            $completionErrors = [];

            if (! filled($lockedProcedureRecord->findings)) {
                $completionErrors['findings'] = 'Record procedure findings before completion.';
            }

            if (! filled($lockedProcedureRecord->outcome)) {
                $completionErrors['outcome'] = 'Record the procedure outcome before completion.';
            }

            if ($lockedProcedureRecord->specimens_taken && ! filled($lockedProcedureRecord->specimen_notes)) {
                $completionErrors['specimen_notes'] = 'Describe the specimens taken before completion.';
            }

            if ($completionErrors !== []) {
                throw ValidationException::withMessages($completionErrors);
            }

            $lockedProcedureRecord->completeFromDoctorWorkflow($lockedActor);

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::ProcedureCompleted,
                subject: $lockedProcedureRecord,
                afterValues: [
                    'procedure_number' => $lockedProcedureRecord->procedure_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'procedure_decision_id' => $lockedDecision->getKey(),
                    'pre_procedure_readiness_id' => $lockedReadiness->getKey(),
                    'doctor_user_id' => $lockedActor->getKey(),
                    'status' => $lockedProcedureRecord->status->value,
                ],
            );

            return $lockedProcedureRecord->refresh();
        }, attempts: 3);
    }
}
