<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Visit;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GrantProcedureFinancialClearance
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Bill $bill): FinancialClearance
    {
        Gate::forUser($actor)->authorize('create', FinancialClearance::class);

        return DB::transaction(function () use ($actor, $bill): FinancialClearance {
            $lockedBill = Bill::query()
                ->with('items:id,bill_id,amount_minor')
                ->lockForUpdate()
                ->find($bill->getKey());

            if (! $lockedBill instanceof Bill
                || $lockedBill->type !== BillType::Procedure
                || $lockedBill->status !== BillStatus::Paid
                || $lockedBill->procedure_billing_handoff_id === null) {
                throw ValidationException::withMessages([
                    'bill' => 'Only a fully paid procedure Bill can receive financial clearance.',
                ]);
            }

            $payment = Payment::query()
                ->where('bill_id', $lockedBill->getKey())
                ->lockForUpdate()
                ->first();
            $receipt = $payment instanceof Payment
                ? Receipt::query()->where('payment_id', $payment->getKey())->lockForUpdate()->first()
                : null;

            if (! $payment instanceof Payment
                || ! $receipt instanceof Receipt
                || $payment->amount_minor <= 0
                || $payment->amount_minor !== $lockedBill->totalAmountMinor()) {
                throw ValidationException::withMessages([
                    'bill' => 'Procedure financial clearance requires the exact successful Payment and Receipt.',
                ]);
            }

            if ($lockedBill->financialClearance()->exists()) {
                throw ValidationException::withMessages([
                    'bill' => 'This procedure Bill has already received financial clearance.',
                ]);
            }

            $lockedHandoff = ProcedureBillingHandoff::query()
                ->lockForUpdate()
                ->find($lockedBill->procedure_billing_handoff_id);
            $lockedDecision = $lockedHandoff instanceof ProcedureBillingHandoff
                ? ProcedureDecision::query()->lockForUpdate()->find($lockedHandoff->procedure_decision_id)
                : null;

            if (! $lockedHandoff instanceof ProcedureBillingHandoff
                || ! $lockedDecision instanceof ProcedureDecision
                || $lockedHandoff->visit_id !== $lockedBill->visit_id
                || ! $lockedHandoff->matchesAuthoritativeDecision($lockedDecision)) {
                throw ValidationException::withMessages([
                    'procedure_billing_handoff' => 'Procedure financial clearance requires the matching authoritative Doctor decision and billing handoff.',
                ]);
            }

            $lockedVisit = Visit::query()->lockForUpdate()->find($lockedBill->visit_id);

            if (! $lockedVisit instanceof Visit || $lockedVisit->status !== VisitStatus::CheckedIn) {
                throw ValidationException::withMessages([
                    'visit' => 'Procedure financial clearance requires the checked-in Visit.',
                ]);
            }

            $financialClearance = new FinancialClearance;
            $financialClearance->bill()->associate($lockedBill);
            $financialClearance->grantedBy()->associate($actor);
            $financialClearance->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::ProcedureFinancialCleared,
                subject: $financialClearance,
                afterValues: [
                    'clearance_number' => $financialClearance->clearance_number,
                    'bill_id' => $lockedBill->getKey(),
                    'bill_number' => $lockedBill->bill_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'payment_id' => $payment->getKey(),
                    'receipt_id' => $receipt->getKey(),
                    'procedure_billing_handoff_id' => $lockedHandoff->getKey(),
                ],
            );

            return $financialClearance->load([
                'bill:id,visit_id,procedure_billing_handoff_id,bill_number,type,status',
                'bill.items:id,bill_id,amount_minor',
                'bill.payment:id,bill_id,payment_number,amount_minor',
                'bill.payment.receipt:id,payment_id,receipt_number',
                'bill.visit:id,patient_id,visit_number,occurred_at,status',
                'bill.visit.patient:id,patient_number,first_name,middle_name,last_name',
                'grantedBy:id,name',
            ]);
        }, attempts: 3);
    }
}
