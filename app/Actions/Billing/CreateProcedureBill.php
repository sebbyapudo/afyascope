<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillType;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ProcedureBillingHandoff;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateProcedureBill
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, ProcedureBillingHandoff $handoff): Bill
    {
        Gate::forUser($actor)->authorize('create', Bill::class);

        return DB::transaction(function () use ($actor, $handoff): Bill {
            $lockedHandoff = ProcedureBillingHandoff::query()
                ->lockForUpdate()
                ->find($handoff->getKey());

            if (! $lockedHandoff instanceof ProcedureBillingHandoff) {
                throw ValidationException::withMessages([
                    'procedure_billing_handoff' => 'The procedure billing handoff does not exist.',
                ]);
            }

            $lockedVisit = Visit::query()
                ->lockForUpdate()
                ->find($lockedHandoff->visit_id);

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->status !== VisitStatus::CheckedIn
                || ! VisitCheckIn::query()->where('visit_id', $lockedVisit->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'visit' => 'Procedure billing requires the checked-in Visit from the Doctor handoff.',
                ]);
            }

            if ($lockedVisit->procedureBill()->exists() || $lockedHandoff->bill()->exists()) {
                throw ValidationException::withMessages([
                    'visit' => 'A procedure Bill already exists for this Visit.',
                ]);
            }

            $lockedService = ServiceCatalogItem::query()
                ->lockForUpdate()
                ->find($lockedHandoff->service_catalog_item_id);

            if (! $lockedService instanceof ServiceCatalogItem
                || ! $lockedService->is_active
                || $lockedService->category !== BillType::Procedure) {
                throw ValidationException::withMessages([
                    'procedure_billing_handoff' => 'The handoff must reference an active procedure service.',
                ]);
            }

            $bill = new Bill;
            $bill->visit()->associate($lockedVisit);
            $bill->procedureBillingHandoff()->associate($lockedHandoff);
            $bill->type = BillType::Procedure;
            $bill->save();

            $billItem = new BillItem;
            $billItem->bill()->associate($bill);
            $billItem->serviceCatalogItem()->associate($lockedService);
            $billItem->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::BillCreated,
                subject: $bill,
                afterValues: [
                    'bill_number' => $bill->bill_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'type' => $bill->type->value,
                    'status' => $bill->status->value,
                    'procedure_billing_handoff_id' => $lockedHandoff->getKey(),
                    'service_catalog_item_id' => $lockedService->getKey(),
                    'amount_minor' => $billItem->amount_minor,
                ],
            );

            return $bill->load([
                'items:id,bill_id,service_catalog_item_id,description,amount_minor',
                'procedureBillingHandoff:id,visit_id,service_catalog_item_id,decided_by_user_id,handoff_number,decided_at',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
            ]);
        }, attempts: 3);
    }
}
