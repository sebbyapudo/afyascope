<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\Receipt;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordProcedurePayment
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Bill $bill, PaymentMethod $paymentMethod): Receipt
    {
        Gate::forUser($actor)->authorize('create', Payment::class);

        return DB::transaction(function () use ($actor, $bill, $paymentMethod): Receipt {
            $lockedBill = Bill::query()
                ->with('items:id,bill_id,amount_minor')
                ->lockForUpdate()
                ->find($bill->getKey());

            if (! $lockedBill instanceof Bill
                || $lockedBill->type !== BillType::Procedure
                || $lockedBill->status !== BillStatus::Open
                || $lockedBill->procedure_billing_handoff_id === null) {
                throw ValidationException::withMessages([
                    'bill' => 'Only an open procedure Bill can receive procedure payment.',
                ]);
            }

            if ($lockedBill->payment()->exists()) {
                throw ValidationException::withMessages([
                    'bill' => 'This procedure Bill has already been paid.',
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
                    'procedure_billing_handoff' => 'Procedure payment requires the matching authoritative Doctor decision and billing handoff.',
                ]);
            }

            $payment = new Payment;
            $payment->bill()->associate($lockedBill);
            $payment->recordedBy()->associate($actor);
            $payment->method = $paymentMethod;
            $payment->save();

            $receipt = new Receipt;
            $receipt->payment()->associate($payment);
            $receipt->save();

            $lockedBill->status = BillStatus::Paid;
            $lockedBill->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::PaymentRecorded,
                subject: $payment,
                afterValues: [
                    'payment_number' => $payment->payment_number,
                    'bill_id' => $lockedBill->getKey(),
                    'bill_number' => $lockedBill->bill_number,
                    'amount_minor' => $payment->amount_minor,
                    'method' => $payment->method->value,
                ],
            );

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::ReceiptIssued,
                subject: $receipt,
                afterValues: [
                    'receipt_number' => $receipt->receipt_number,
                    'payment_id' => $payment->getKey(),
                    'bill_id' => $lockedBill->getKey(),
                ],
            );

            return $receipt->load([
                'payment:id,bill_id,payment_number,amount_minor,method,recorded_by_user_id,recorded_at',
                'payment.recordedBy:id,name',
                'payment.bill:id,visit_id,procedure_billing_handoff_id,bill_number,type,status',
                'payment.bill.visit:id,patient_id,visit_number,occurred_at,status',
                'payment.bill.visit.patient:id,patient_number,first_name,middle_name,last_name',
            ]);
        }, attempts: 3);
    }
}
