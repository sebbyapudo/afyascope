<?php

namespace App\Actions\Nursing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillType;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\Models\Visit;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartPreProcedureReadiness
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Visit $visit): PreProcedureReadiness
    {
        Gate::forUser($actor)->authorize('create', PreProcedureReadiness::class);

        return DB::transaction(function () use ($actor, $visit): PreProcedureReadiness {
            $lockedVisit = Visit::query()->lockForUpdate()->find($visit->getKey());

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::CheckedIn->value) {
                throw ValidationException::withMessages([
                    'visit' => 'Nursing preparation requires a checked-in Visit.',
                ]);
            }

            $lockedDecision = ProcedureDecision::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();
            $lockedHandoff = ProcedureBillingHandoff::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedDecision instanceof ProcedureDecision
                || $lockedDecision->outcome !== ProcedureDecisionOutcome::ProcedureRequired
                || ! $lockedHandoff instanceof ProcedureBillingHandoff
                || ! $lockedHandoff->matchesAuthoritativeDecision($lockedDecision)) {
                throw ValidationException::withMessages([
                    'visit' => 'Nursing preparation requires the authoritative procedure-required decision and handoff.',
                ]);
            }

            $lockedBill = Bill::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->where('procedure_billing_handoff_id', $lockedHandoff->getKey())
                ->where('type', BillType::Procedure->value)
                ->lockForUpdate()
                ->first();
            $financialClearance = $lockedBill instanceof Bill
                ? FinancialClearance::query()
                    ->where('bill_id', $lockedBill->getKey())
                    ->lockForUpdate()
                    ->first()
                : null;

            if (! $lockedBill instanceof Bill || ! $financialClearance instanceof FinancialClearance) {
                throw ValidationException::withMessages([
                    'visit' => 'Nursing preparation requires completed procedure financial clearance.',
                ]);
            }

            $existingReadiness = PreProcedureReadiness::query()
                ->where('visit_id', $lockedVisit->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingReadiness instanceof PreProcedureReadiness) {
                throw ValidationException::withMessages([
                    'visit' => 'Nursing preparation has already started for this Visit.',
                ]);
            }

            $lockedActor = User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->whereHas('role', function (Builder $query): void {
                    $query->where('slug', StaffRole::Nurse->value);
                })
                ->lockForUpdate()
                ->first();

            if (! $lockedActor instanceof User) {
                throw ValidationException::withMessages([
                    'actor' => 'Nursing preparation requires an active Nurse.',
                ]);
            }

            $readiness = PreProcedureReadiness::startForNursingWorkflow(
                $lockedVisit,
                $lockedDecision,
                $lockedActor,
            );

            $this->recordAuditLog->handle(
                actor: $lockedActor,
                action: AuditAction::NursingPreparationStarted,
                subject: $readiness,
                afterValues: [
                    'readiness_number' => $readiness->readiness_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'procedure_decision_id' => $lockedDecision->getKey(),
                    'nurse_user_id' => $lockedActor->getKey(),
                    'status' => $readiness->status->value,
                ],
            );

            return $readiness->refresh();
        }, attempts: 3);
    }
}
