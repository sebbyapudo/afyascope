<?php

namespace App\Http\Controllers;

use App\Actions\Billing\GrantConsultationFinancialClearance;
use App\BillStatus;
use App\BillType;
use App\Http\Requests\StoreConsultationFinancialClearanceRequest;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationFinancialClearanceController extends Controller
{
    public function index(): Response
    {
        $bills = Bill::query()
            ->select(['id', 'visit_id', 'bill_number', 'type', 'status', 'created_at'])
            ->where('type', BillType::Consultation->value)
            ->where('status', BillStatus::Paid->value)
            ->whereHas('payment.receipt')
            ->whereDoesntHave('financialClearance')
            ->with([
                'items:id,bill_id,amount_minor',
                'payment:id,bill_id,payment_number,amount_minor,recorded_at',
                'payment.receipt:id,payment_id,receipt_number',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15);

        return Inertia::render('billing/clearances/index', [
            'bills' => [
                'data' => $bills->getCollection()
                    ->map(fn (Bill $bill): array => $this->billData($bill))
                    ->values(),
                'pagination' => [
                    'currentPage' => $bills->currentPage(),
                    'from' => $bills->firstItem(),
                    'lastPage' => $bills->lastPage(),
                    'to' => $bills->lastItem(),
                    'total' => $bills->total(),
                ],
            ],
        ]);
    }

    public function create(Bill $bill): Response|RedirectResponse
    {
        abort_unless($bill->type === BillType::Consultation, 404);

        $existingClearance = $bill->financialClearance()->first();

        if ($existingClearance instanceof FinancialClearance) {
            return redirect()->route('billing.clearances.show', $existingClearance);
        }

        abort_unless($bill->status === BillStatus::Paid, 404);

        $bill->load([
            'items:id,bill_id,amount_minor',
            'payment:id,bill_id,payment_number,amount_minor,recorded_at',
            'payment.receipt:id,payment_id,receipt_number',
            'visit:id,patient_id,visit_number,occurred_at,status',
            'visit.patient:id,patient_number,first_name,middle_name,last_name',
        ]);
        abort_unless(
            $bill->payment instanceof Payment && $bill->payment->receipt instanceof Receipt,
            409,
        );

        return Inertia::render('billing/clearances/create', [
            'bill' => $this->billData($bill),
        ]);
    }

    public function store(
        StoreConsultationFinancialClearanceRequest $request,
        Bill $bill,
        GrantConsultationFinancialClearance $grantConsultationFinancialClearance,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $financialClearance = $grantConsultationFinancialClearance->handle($actor, $bill);

        return redirect()->route('billing.clearances.show', $financialClearance)->with(
            'status',
            "Financial clearance {$financialClearance->clearance_number} was granted.",
        );
    }

    public function show(Request $request, FinancialClearance $financialClearance): Response
    {
        $financialClearance->load([
            'bill:id,visit_id,bill_number,type,status,created_at',
            'bill.items:id,bill_id,amount_minor',
            'bill.payment:id,bill_id,payment_number,amount_minor,recorded_at',
            'bill.payment.receipt:id,payment_id,receipt_number',
            'bill.visit:id,patient_id,visit_number,occurred_at,status',
            'bill.visit.patient:id,patient_number,first_name,middle_name,last_name',
            'grantedBy:id,name',
        ]);
        abort_unless($financialClearance->bill->type === BillType::Consultation, 404);
        $status = $request->session()->get('status');

        return Inertia::render('billing/clearances/show', [
            'clearance' => $this->clearanceData($financialClearance),
            'status' => is_string($status) ? $status : null,
        ]);
    }

    /**
     * @return array{id: int, billNumber: string, billStatus: array{value: string, label: string}, totalAmountMinor: int, createdAt: string|null, payment: array{paymentNumber: string, amountMinor: int, recordedAt: string, receipt: array{id: int, receiptNumber: string}}, visit: array{visitNumber: string, occurredAt: string, nextStep: string}, patient: array{patientNumber: string, name: string}}
     */
    private function billData(Bill $bill): array
    {
        /** @var Payment $payment */
        $payment = $bill->payment;
        /** @var Receipt $receipt */
        $receipt = $payment->receipt;
        /** @var Visit $visit */
        $visit = $bill->visit;
        /** @var Patient $patient */
        $patient = $visit->patient;

        return [
            'id' => $bill->id,
            'billNumber' => $bill->bill_number,
            'billStatus' => [
                'value' => $bill->status->value,
                'label' => $bill->status->displayName(),
            ],
            'totalAmountMinor' => $bill->totalAmountMinor(),
            'createdAt' => $bill->created_at?->toIso8601String(),
            'payment' => [
                'paymentNumber' => $payment->payment_number,
                'amountMinor' => $payment->amount_minor,
                'recordedAt' => $payment->recorded_at->toIso8601String(),
                'receipt' => [
                    'id' => $receipt->id,
                    'receiptNumber' => $receipt->receipt_number,
                ],
            ],
            'visit' => [
                'visitNumber' => $visit->visit_number,
                'occurredAt' => $visit->occurred_at->toIso8601String(),
                'nextStep' => $visit->workflowMessage(),
            ],
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
        ];
    }

    /**
     * @return array{id: int, clearanceNumber: string, grantedAt: string, grantedBy: string, bill: array{id: int, billNumber: string, status: array{value: string, label: string}, totalAmountMinor: int}, payment: array{paymentNumber: string, amountMinor: int, receipt: array{id: int, receiptNumber: string}}, visit: array{visitNumber: string, occurredAt: string, status: array{value: string, label: string}, nextStep: string}, patient: array{patientNumber: string, name: string}}
     */
    private function clearanceData(FinancialClearance $financialClearance): array
    {
        $billData = $this->billData($financialClearance->bill);
        /** @var Visit $visit */
        $visit = $financialClearance->bill->visit;

        return [
            'id' => $financialClearance->id,
            'clearanceNumber' => $financialClearance->clearance_number,
            'grantedAt' => $financialClearance->granted_at->toIso8601String(),
            'grantedBy' => $financialClearance->grantedBy->name,
            'bill' => [
                'id' => $billData['id'],
                'billNumber' => $billData['billNumber'],
                'status' => $billData['billStatus'],
                'totalAmountMinor' => $billData['totalAmountMinor'],
            ],
            'payment' => $billData['payment'],
            'visit' => [
                'visitNumber' => $visit->visit_number,
                'occurredAt' => $visit->occurred_at->toIso8601String(),
                'status' => [
                    'value' => $visit->status->value,
                    'label' => $visit->status->displayName(),
                ],
                'nextStep' => $visit->workflowMessage(),
            ],
            'patient' => $billData['patient'],
        ];
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
