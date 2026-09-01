<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Visit;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GrantConsultationFinancialClearance
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Bill $bill): FinancialClearance
    {
        Gate::forUser($actor)->authorize('create', FinancialClearance::class);

        return DB::transaction(function () use ($actor, $bill): FinancialClearance {
            $lockedBill = Bill::query()
                ->with([
                    'items:id,bill_id,amount_minor',
                    'payment:id,bill_id,payment_number,amount_minor',
                    'payment.receipt:id,payment_id,receipt_number',
                ])
                ->lockForUpdate()
                ->find($bill->getKey());

            if (! $lockedBill instanceof Bill
                || $lockedBill->type !== BillType::Consultation
                || $lockedBill->status !== BillStatus::Paid) {
                throw ValidationException::withMessages([
                    'bill' => 'Only a fully paid consultation Bill can receive financial clearance.',
                ]);
            }

            $payment = $lockedBill->payment;
            $receipt = $payment instanceof Payment ? $payment->receipt : null;

            if (! $payment instanceof Payment
                || ! $receipt instanceof Receipt
                || $payment->amount_minor <= 0
                || $payment->amount_minor !== $lockedBill->totalAmountMinor()) {
                throw ValidationException::withMessages([
                    'bill' => 'Financial clearance requires the exact successful Payment and Receipt.',
                ]);
            }

            if ($lockedBill->financialClearance()->exists()) {
                throw ValidationException::withMessages([
                    'bill' => 'This consultation Bill has already received financial clearance.',
                ]);
            }

            $lockedVisit = Visit::query()
                ->lockForUpdate()
                ->find($lockedBill->visit_id);

            if (! $lockedVisit instanceof Visit
                || $lockedVisit->getRawOriginal('status') !== VisitStatus::Created->value) {
                throw ValidationException::withMessages([
                    'visit' => 'Financial clearance requires a created Visit.',
                ]);
            }

            $financialClearance = new FinancialClearance;
            $financialClearance->bill()->associate($lockedBill);
            $financialClearance->grantedBy()->associate($actor);
            $financialClearance->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::ConsultationFinancialCleared,
                subject: $financialClearance,
                afterValues: [
                    'clearance_number' => $financialClearance->clearance_number,
                    'bill_id' => $lockedBill->getKey(),
                    'bill_number' => $lockedBill->bill_number,
                    'visit_id' => $lockedVisit->getKey(),
                    'payment_id' => $payment->getKey(),
                    'receipt_id' => $receipt->getKey(),
                ],
            );

            return $financialClearance->load([
                'bill:id,visit_id,bill_number,type,status',
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
