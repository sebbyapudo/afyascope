<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $visits = Visit::query()
            ->select(['id', 'patient_id', 'appointment_id', 'visit_number', 'occurred_at', 'status'])
            ->with([
                'patient:id,patient_number,first_name,middle_name,last_name',
                'appointment:id,appointment_number',
                'consultationBill.items:id,bill_id,amount_minor',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $searchPrefix = addcslashes($search, '\\%_').'%';

                $query->where(function (Builder $searchQuery) use ($searchPrefix): void {
                    $searchQuery
                        ->where('visit_number', 'like', $searchPrefix)
                        ->orWhereHas('patient', function (Builder $patientQuery) use ($searchPrefix): void {
                            $patientQuery
                                ->where('patient_number', 'like', $searchPrefix)
                                ->orWhere('first_name', 'like', $searchPrefix)
                                ->orWhere('middle_name', 'like', $searchPrefix)
                                ->orWhere('last_name', 'like', $searchPrefix);
                        });
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('visits/index', [
            'visits' => [
                'data' => $visits->getCollection()
                    ->map(fn (Visit $visit): array => $this->visitData($visit))
                    ->values(),
                'pagination' => [
                    'currentPage' => $visits->currentPage(),
                    'from' => $visits->firstItem(),
                    'lastPage' => $visits->lastPage(),
                    'to' => $visits->lastItem(),
                    'total' => $visits->total(),
                ],
            ],
            'filters' => ['q' => $search],
        ]);
    }

    public function show(Request $request, Visit $visit): Response
    {
        $visit->load([
            'patient:id,patient_number,first_name,middle_name,last_name',
            'appointment:id,appointment_number',
            'consultationBill.items:id,bill_id,amount_minor',
        ]);
        $status = $request->session()->get('status');

        return Inertia::render('visits/show', [
            'visit' => $this->visitData($visit),
            'status' => is_string($status) ? $status : null,
        ]);
    }

    /**
     * @return array{id: int, visitNumber: string, occurredAt: string, status: array{value: string, label: string}, nextStep: string, consultationBill: array{billNumber: string, status: array{value: string, label: string}, totalAmountMinor: int}|null, appointment: array{id: int, appointmentNumber: string}|null, patient: array{id: int, patientNumber: string, name: string}}
     */
    private function visitData(Visit $visit): array
    {
        /** @var Patient $patient */
        $patient = $visit->patient;
        $consultationBill = $visit->consultationBill;

        return [
            'id' => $visit->id,
            'visitNumber' => $visit->visit_number,
            'occurredAt' => $visit->occurred_at->toIso8601String(),
            'status' => [
                'value' => $visit->status->value,
                'label' => $visit->status->displayName(),
            ],
            'nextStep' => $visit->workflowMessage(),
            'consultationBill' => $consultationBill instanceof Bill ? [
                'billNumber' => $consultationBill->bill_number,
                'status' => [
                    'value' => $consultationBill->status->value,
                    'label' => $consultationBill->status->displayName(),
                ],
                'totalAmountMinor' => $consultationBill->totalAmountMinor(),
            ] : null,
            'appointment' => $visit->appointment instanceof Appointment ? [
                'id' => $visit->appointment->id,
                'appointmentNumber' => $visit->appointment->appointment_number,
            ] : null,
            'patient' => [
                'id' => $patient->id,
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
