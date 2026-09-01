<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillType;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateConsultationBill
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Visit $visit, ServiceCatalogItem $serviceCatalogItem): Bill
    {
        Gate::forUser($actor)->authorize('create', Bill::class);

        return DB::transaction(function () use ($actor, $visit, $serviceCatalogItem): Bill {
            $lockedVisit = Visit::query()
                ->lockForUpdate()
                ->find($visit->getKey());

            if (! $lockedVisit instanceof Visit) {
                throw ValidationException::withMessages([
                    'visit' => 'The selected Visit does not exist.',
                ]);
            }

            if ($lockedVisit->consultationBill()->exists()) {
                throw ValidationException::withMessages([
                    'visit' => 'A consultation Bill already exists for this Visit.',
                ]);
            }

            $lockedServiceCatalogItem = ServiceCatalogItem::query()
                ->lockForUpdate()
                ->find($serviceCatalogItem->getKey());

            if (! $lockedServiceCatalogItem instanceof ServiceCatalogItem
                || ! $lockedServiceCatalogItem->is_active
                || $lockedServiceCatalogItem->category !== BillType::Consultation) {
                throw ValidationException::withMessages([
                    'service_catalog_item_id' => 'Select an active consultation service.',
                ]);
            }

            $bill = new Bill;
            $bill->visit()->associate($lockedVisit);
            $bill->type = BillType::Consultation;
            $bill->save();

            $billItem = new BillItem;
            $billItem->bill()->associate($bill);
            $billItem->serviceCatalogItem()->associate($lockedServiceCatalogItem);
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
                    'service_catalog_item_id' => $lockedServiceCatalogItem->getKey(),
                    'amount_minor' => $billItem->amount_minor,
                ],
            );

            return $bill->load([
                'items:id,bill_id,service_catalog_item_id,description,amount_minor',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
            ]);
        });
    }
}
