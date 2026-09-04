<?php

namespace App\Http\Controllers;

use App\Actions\Billing\CreateProcedureBill;
use App\Http\Requests\StoreProcedureBillRequest;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProcedureBillingController extends Controller
{
    public function index(): Response
    {
        $handoffs = ProcedureBillingHandoff::query()
            ->awaitingBill()
            ->select([
                'id',
                'procedure_decision_id',
                'visit_id',
                'service_catalog_item_id',
                'decided_by_user_id',
                'handoff_number',
                'decided_at',
            ])
            ->with([
                'procedureDecision:id,consultation_id,visit_id,doctor_user_id,service_catalog_item_id,decision_number,outcome,decided_at',
                'serviceCatalogItem:id,name,category,unit_price_minor,is_active',
                'decidedBy:id,name',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
            ])
            ->paginate(15);

        return Inertia::render('billing/procedures/index', [
            'handoffs' => [
                'data' => $handoffs->getCollection()
                    ->map(fn (ProcedureBillingHandoff $handoff): array => $this->handoffData($handoff))
                    ->values(),
                'pagination' => [
                    'currentPage' => $handoffs->currentPage(),
                    'from' => $handoffs->firstItem(),
                    'lastPage' => $handoffs->lastPage(),
                    'to' => $handoffs->lastItem(),
                    'total' => $handoffs->total(),
                ],
            ],
        ]);
    }

    public function create(ProcedureBillingHandoff $procedureBillingHandoff): Response|RedirectResponse
    {
        $existingBill = $procedureBillingHandoff->bill()->first();

        if ($existingBill instanceof Bill) {
            return redirect()->route('billing.bills.show', $existingBill);
        }

        $handoff = $this->eligibleHandoff($procedureBillingHandoff);

        return Inertia::render('billing/procedures/create', [
            'handoff' => $this->handoffData($handoff),
        ]);
    }

    public function store(
        StoreProcedureBillRequest $request,
        ProcedureBillingHandoff $procedureBillingHandoff,
        CreateProcedureBill $createProcedureBill,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $bill = $createProcedureBill->handle($actor, $procedureBillingHandoff);

        return redirect()->route('billing.bills.show', $bill)->with(
            'status',
            "Procedure Bill {$bill->bill_number} was created.",
        );
    }

    private function eligibleHandoff(ProcedureBillingHandoff $procedureBillingHandoff): ProcedureBillingHandoff
    {
        $handoff = ProcedureBillingHandoff::query()
            ->awaitingBill()
            ->with([
                'procedureDecision:id,consultation_id,visit_id,doctor_user_id,service_catalog_item_id,decision_number,outcome,decided_at',
                'serviceCatalogItem:id,name,category,unit_price_minor,is_active',
                'decidedBy:id,name',
                'visit:id,patient_id,visit_number,occurred_at,status',
                'visit.patient:id,patient_number,first_name,middle_name,last_name',
            ])
            ->find($procedureBillingHandoff->getKey());

        abort_unless($handoff instanceof ProcedureBillingHandoff, 404);

        return $handoff;
    }

    /**
     * @return array{id: int, handoffNumber: string, decidedAt: string, decisionNumber: string, decidedBy: string, procedure: array{name: string, amountMinor: int}, visit: array{visitNumber: string, occurredAt: string, status: array{value: string, label: string}, nextStep: string}, patient: array{patientNumber: string, name: string}}
     */
    private function handoffData(ProcedureBillingHandoff $handoff): array
    {
        /** @var ProcedureDecision $decision */
        $decision = $handoff->procedureDecision;
        /** @var ServiceCatalogItem $service */
        $service = $handoff->serviceCatalogItem;
        /** @var Visit $visit */
        $visit = $handoff->visit;
        /** @var Patient $patient */
        $patient = $visit->patient;

        return [
            'id' => $handoff->id,
            'handoffNumber' => $handoff->handoff_number,
            'decidedAt' => $handoff->decided_at->toIso8601String(),
            'decisionNumber' => $decision->decision_number,
            'decidedBy' => $handoff->decidedBy->name,
            'procedure' => [
                'name' => $service->name,
                'amountMinor' => $service->unit_price_minor,
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
