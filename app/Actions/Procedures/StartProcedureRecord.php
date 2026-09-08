<?php

namespace App\Actions\Procedures;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\Consultation;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\ServiceCatalogItem;
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

class StartProcedureRecord
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Visit $visit): ProcedureRecord
    {
        Gate::forUser($actor)->authorize('create', ProcedureRecord::class);

        return DB::transaction(function () use ($actor, $visit): ProcedureRecord {
            $lockedVisit = Visit::query()->lockForUpdate()->find($visit->getKey());

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::CheckedIn->value) {
                throw ValidationException::withMessages([
                    'visit' => 'Procedure start requires a checked-in Visit.',
                ]);
            }

            $lockedDecision = ProcedureDecision::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();
            $lockedReadiness = PreProcedureReadiness::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();
            $existingProcedureRecord = ProcedureRecord::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingProcedureRecord instanceof ProcedureRecord) {
                throw ValidationException::withMessages([
                    'visit' => 'A procedure record already exists for this Visit.',
                ]);
            }

            if (! $lockedDecision instanceof ProcedureDecision
                || $lockedDecision->outcome !== ProcedureDecisionOutcome::ProcedureRequired
                || ! $lockedReadiness instanceof PreProcedureReadiness
                || $lockedReadiness->procedure_decision_id !== $lockedDecision->getKey()
                || $lockedReadiness->status !== PreProcedureReadinessStatus::Ready) {
                throw ValidationException::withMessages([
                    'visit' => 'Procedure start requires the authoritative completed Nurse readiness handoff.',
                ]);
            }

            $lockedConsultation = Consultation::query()
                ->lockForUpdate()
                ->find($lockedDecision->consultation_id);
            $lockedService = ServiceCatalogItem::query()
                ->lockForUpdate()
                ->find($lockedDecision->service_catalog_item_id);
            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Doctor->value);
                })
                ->lockForUpdate()
                ->first();

            if (! $lockedConsultation instanceof Consultation
                || $lockedConsultation->getRawOriginal('status') !== ConsultationStatus::InProgress->value
                || $lockedConsultation->visit_id !== $lockedVisit->getKey()
                || $lockedConsultation->doctor_user_id !== $lockedDecision->doctor_user_id
                || ! $lockedService instanceof ServiceCatalogItem
                || $lockedService->getKey() !== $lockedDecision->service_catalog_item_id
                || ! $lockedActor instanceof User
                || $lockedDecision->doctor_user_id !== $lockedActor->getKey()) {
                throw ValidationException::withMessages([
                    'actor' => 'Only the responsible active Doctor may start this procedure.',
                ]);
            }

            $lockedDecision->setRelation('consultation', $lockedConsultation);

            $procedureRecord = ProcedureRecord::startFromDoctorWorkflow(
                $lockedVisit,
                $lockedDecision,
                $lockedReadiness,
                $lockedService,
                $lockedActor,
            );

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::ProcedureStarted,
                subject: $procedureRecord,
                afterValues: [
                    'procedure_number' => $procedureRecord->procedure_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'procedure_decision_id' => $lockedDecision->getKey(),
                    'pre_procedure_readiness_id' => $lockedReadiness->getKey(),
                    'doctor_user_id' => $lockedActor->getKey(),
                    'status' => $procedureRecord->status->value,
                ],
            );

            return $procedureRecord->refresh();
        }, attempts: 3);
    }
}
