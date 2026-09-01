<?php

namespace App\Http\Controllers;

use App\BillType;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Bill $bill): Response
    {
        abort_unless($bill->type === BillType::Consultation, 404);

        $bill->load([
            'items:id,bill_id,description,amount_minor',
            'visit:id,patient_id,visit_number,occurred_at,status',
            'visit.patient:id,patient_number,first_name,middle_name,last_name',
        ]);
        $status = $request->session()->get('status');

        /** @var Visit $visit */
        $visit = $bill->visit;
        /** @var Patient $patient */
        $patient = $visit->patient;

        return Inertia::render('billing/show', [
            'bill' => [
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
                'items' => $bill->items
                    ->map(fn (BillItem $item): array => [
                        'id' => $item->id,
                        'description' => $item->description,
                        'amountMinor' => $item->amount_minor,
                    ])
                    ->values(),
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
