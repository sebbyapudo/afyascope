<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Receipt $receipt): Response
    {
        $receipt->load([
            'payment:id,bill_id,payment_number,amount_minor,method,recorded_by_user_id,recorded_at',
            'payment.recordedBy:id,name',
            'payment.bill:id,visit_id,bill_number,type,status,created_at',
            'payment.bill.visit:id,patient_id,visit_number,occurred_at,status',
            'payment.bill.visit.patient:id,patient_number,first_name,middle_name,last_name',
        ]);
        $status = $request->session()->get('status');

        /** @var Payment $payment */
        $payment = $receipt->payment;
        /** @var User $recordedBy */
        $recordedBy = $payment->recordedBy;
        /** @var Bill $bill */
        $bill = $payment->bill;
        /** @var Visit $visit */
        $visit = $bill->visit;
        /** @var Patient $patient */
        $patient = $visit->patient;

        return Inertia::render('billing/receipts/show', [
            'receipt' => [
                'receiptNumber' => $receipt->receipt_number,
                'issuedAt' => $receipt->issued_at->toIso8601String(),
                'payment' => [
                    'paymentNumber' => $payment->payment_number,
                    'amountMinor' => $payment->amount_minor,
                    'method' => [
                        'value' => $payment->method->value,
                        'label' => $payment->method->displayName(),
                    ],
                    'recordedAt' => $payment->recorded_at->toIso8601String(),
                    'recordedBy' => $recordedBy->name,
                ],
                'bill' => [
                    'billNumber' => $bill->bill_number,
                    'type' => [
                        'value' => $bill->type->value,
                        'label' => $bill->type->displayName(),
                    ],
                    'status' => [
                        'value' => $bill->status->value,
                        'label' => $bill->status->displayName(),
                    ],
                ],
                'visit' => [
                    'visitNumber' => $visit->visit_number,
                    'occurredAt' => $visit->occurred_at->toIso8601String(),
                    'status' => [
                        'value' => $visit->status->value,
                        'label' => $visit->status->displayName(),
                    ],
                    'nextStep' => $visit->workflowMessage(),
                ],
                'patient' => [
                    'patientNumber' => $patient->patient_number,
                    'name' => $this->patientName($patient),
                ],
            ],
            'status' => is_string($status) ? $status : null,
        ]);
    }

    private function patientName(Patient $patient): string
    {
        return collect([
            $patient->first_name,
            $patient->middle_name,
            $patient->last_name,
        ])->filter()->implode(' ');
    }
}
