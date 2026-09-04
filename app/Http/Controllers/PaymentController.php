<?php

namespace App\Http\Controllers;

use App\Actions\Billing\RecordConsultationPayment;
use App\Actions\Billing\RecordProcedurePayment;
use App\BillStatus;
use App\BillType;
use App\Http\Requests\StoreConsultationPaymentRequest;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Visit;
use App\PaymentMethod;
use App\ProcedureDecisionOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(): Response
    {
        $bills = Bill::query()
            ->select(['id', 'visit_id', 'procedure_billing_handoff_id', 'bill_number', 'type', 'status', 'created_at'])
            ->where(function (Builder $query): void {
                $query
                    ->where('type', BillType::Consultation->value)
                    ->orWhere(function (Builder $procedureQuery): void {
                        $procedureQuery
                            ->where('type', BillType::Procedure->value)
                            ->whereHas('procedureBillingHandoff.procedureDecision', function (Builder $decisionQuery): void {
                                $decisionQuery->where('outcome', ProcedureDecisionOutcome::ProcedureRequired->value);
                            });
                    });
            })
            ->where('status', BillStatus::Open->value)
            ->whereDoesntHave('payment')
            ->with([
                'items:id,bill_id,amount_minor',
                'visit:id,patient_id,visit_number,occurred_at',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15);

        return Inertia::render('billing/payments/index', [
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
        abort_unless(in_array($bill->type, [BillType::Consultation, BillType::Procedure], true), 404);

        if ($bill->type === BillType::Procedure) {
            abort_unless($this->hasValidProcedureFoundation($bill), 404);
        }

        $existingPayment = $bill->payment()->with('receipt')->first();

        if ($existingPayment instanceof Payment) {
            $receipt = $existingPayment->receipt;

            abort_unless($receipt instanceof Receipt, 409);

            return redirect()->route('billing.receipts.show', $receipt);
        }

        abort_unless($bill->status === BillStatus::Open, 404);

        $bill->load([
            'items:id,bill_id,description,amount_minor',
            'visit:id,patient_id,visit_number,occurred_at',
            'visit.patient:id,patient_number,first_name,middle_name,last_name',
        ]);

        return Inertia::render('billing/payments/create', [
            'bill' => $this->billData($bill, includeItems: true),
            'paymentMethods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->displayName(),
                ])
                ->values(),
        ]);
    }

    public function store(
        StoreConsultationPaymentRequest $request,
        Bill $bill,
        RecordConsultationPayment $recordConsultationPayment,
        RecordProcedurePayment $recordProcedurePayment,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $receipt = match ($bill->type) {
            BillType::Consultation => $recordConsultationPayment->handle(
                $actor,
                $bill,
                $request->paymentMethod(),
            ),
            BillType::Procedure => $recordProcedurePayment->handle(
                $actor,
                $bill,
                $request->paymentMethod(),
            ),
        };

        return redirect()->route('billing.receipts.show', $receipt)->with(
            'status',
            "Payment recorded and Receipt {$receipt->receipt_number} issued.",
        );
    }

    /**
     * @return array{id: int, billNumber: string, type: array{value: string, label: string}, status: array{value: string, label: string}, totalAmountMinor: int, createdAt: string|null, items?: list<array{id: int, description: string, amountMinor: int}>, visit: array{visitNumber: string, occurredAt: string}, patient: array{patientNumber: string, name: string}}
     */
    private function billData(Bill $bill, bool $includeItems = false): array
    {
        /** @var Visit $visit */
        $visit = $bill->visit;
        /** @var Patient $patient */
        $patient = $visit->patient;

        $data = [
            'id' => $bill->id,
            'billNumber' => $bill->bill_number,
            'type' => [
                'value' => $bill->type->value,
                'label' => $bill->type->displayName(),
            ],
            'status' => [
                'value' => $bill->status->value,
                'label' => $bill->status->displayName(),
            ],
            'totalAmountMinor' => $bill->totalAmountMinor(),
            'createdAt' => $bill->created_at?->toIso8601String(),
            'visit' => [
                'visitNumber' => $visit->visit_number,
                'occurredAt' => $visit->occurred_at->toIso8601String(),
            ],
            'patient' => [
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
        ];

        if ($includeItems) {
            $data['items'] = array_values(
                $bill->items
                    ->map(fn (BillItem $item): array => [
                        'id' => $item->id,
                        'description' => $item->description,
                        'amountMinor' => $item->amount_minor,
                    ])
                    ->all(),
            );
        }

        return $data;
    }

    private function patientName(Patient $patient): string
    {
        return collect([
            $patient->first_name,
            $patient->middle_name,
            $patient->last_name,
        ])->filter()->implode(' ');
    }

    private function hasValidProcedureFoundation(Bill $bill): bool
    {
        $bill->loadMissing('procedureBillingHandoff.procedureDecision');
        $handoff = $bill->procedureBillingHandoff;
        $decision = $handoff instanceof ProcedureBillingHandoff
            ? $handoff->procedureDecision
            : null;

        return $handoff instanceof ProcedureBillingHandoff
            && $decision instanceof ProcedureDecision
            && $handoff->visit_id === $bill->visit_id
            && $handoff->matchesAuthoritativeDecision($decision);
    }
}
