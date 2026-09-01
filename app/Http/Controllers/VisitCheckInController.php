<?php

namespace App\Http\Controllers;

use App\Actions\Visits\CheckInVisit;
use App\BillStatus;
use App\BillType;
use App\Http\Requests\StoreVisitCheckInRequest;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitCheckInController extends Controller
{
    public function index(): Response
    {
        $visits = Visit::query()
            ->select(['id', 'patient_id', 'appointment_id', 'visit_number', 'occurred_at', 'status'])
            ->where('status', VisitStatus::Created->value)
            ->whereDoesntHave('checkIn')
            ->whereHas('consultationBill', function (Builder $query): void {
                $query
                    ->where('type', BillType::Consultation->value)
                    ->where('status', BillStatus::Paid->value)
                    ->whereHas('payment.receipt')
                    ->whereHas('financialClearance');
            })
            ->with([
                'patient:id,patient_number,first_name,middle_name,last_name',
                'consultationBill:id,visit_id,bill_number,type,status',
                'consultationBill.financialClearance:id,bill_id,clearance_number,granted_at',
            ])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->paginate(15);

        return Inertia::render('check-ins/index', [
            'visits' => [
                'data' => $visits->getCollection()
                    ->map(fn (Visit $visit): array => $this->eligibleVisitData($visit))
                    ->values(),
                'pagination' => [
                    'currentPage' => $visits->currentPage(),
                    'from' => $visits->firstItem(),
                    'lastPage' => $visits->lastPage(),
                    'to' => $visits->lastItem(),
                    'total' => $visits->total(),
                ],
            ],
        ]);
    }

    public function create(Visit $visit): Response|RedirectResponse
    {
        $existingCheckIn = $visit->checkIn()->first();

        if ($existingCheckIn instanceof VisitCheckIn) {
            return redirect()->route('check-ins.show', $existingCheckIn);
        }

        $this->loadEligibleVisitContext($visit);
        abort_unless($this->isEligible($visit), 404);

        return Inertia::render('check-ins/create', [
            'visit' => $this->eligibleVisitData($visit),
        ]);
    }

    public function store(
        StoreVisitCheckInRequest $request,
        Visit $visit,
        CheckInVisit $checkInVisit,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $visitCheckIn = $checkInVisit->handle($actor, $visit);

        return redirect()->route('check-ins.show', $visitCheckIn)->with(
            'status',
            "Check-in {$visitCheckIn->check_in_number} was completed.",
        );
    }

    public function show(Request $request, VisitCheckIn $visitCheckIn): Response
    {
        $visitCheckIn->load([
            'visit:id,patient_id,appointment_id,visit_number,occurred_at,status',
            'visit.patient:id,patient_number,first_name,middle_name,last_name',
            'visit.consultationBill:id,visit_id,bill_number,type,status',
            'visit.consultationBill.financialClearance:id,bill_id,clearance_number,granted_at',
            'checkedInBy:id,name',
        ]);
        $status = $request->session()->get('status');

        return Inertia::render('check-ins/show', [
            'checkIn' => $this->checkInData($visitCheckIn),
            'status' => is_string($status) ? $status : null,
        ]);
    }

    private function loadEligibleVisitContext(Visit $visit): void
    {
        $visit->load([
            'patient:id,patient_number,first_name,middle_name,last_name',
            'consultationBill:id,visit_id,bill_number,type,status',
            'consultationBill.payment:id,bill_id,amount_minor',
            'consultationBill.payment.receipt:id,payment_id',
            'consultationBill.financialClearance:id,bill_id,clearance_number,granted_at',
        ]);
    }

    private function isEligible(Visit $visit): bool
    {
        $bill = $visit->consultationBill;

        return $visit->status === VisitStatus::Created
            && $bill instanceof Bill
            && $bill->type === BillType::Consultation
            && $bill->status === BillStatus::Paid
            && $bill->payment?->receipt !== null
            && $bill->financialClearance instanceof FinancialClearance;
    }

    /**
     * @return array{id: int, visitNumber: string, occurredAt: string, status: array{value: string, label: string}, nextStep: string, clearance: array{clearanceNumber: string, grantedAt: string}, patient: array{id: int, patientNumber: string, name: string}}
     */
    private function eligibleVisitData(Visit $visit): array
    {
        /** @var Patient $patient */
        $patient = $visit->patient;
        /** @var FinancialClearance $financialClearance */
        $financialClearance = $visit->consultationBill->financialClearance;

        return [
            'id' => $visit->id,
            'visitNumber' => $visit->visit_number,
            'occurredAt' => $visit->occurred_at->toIso8601String(),
            'status' => [
                'value' => $visit->status->value,
                'label' => $visit->status->displayName(),
            ],
            'nextStep' => $visit->workflowMessage(),
            'clearance' => [
                'clearanceNumber' => $financialClearance->clearance_number,
                'grantedAt' => $financialClearance->granted_at->toIso8601String(),
            ],
            'patient' => [
                'id' => $patient->id,
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
        ];
    }

    /**
     * @return array{id: int, checkInNumber: string, checkedInAt: string, checkedInBy: string, visit: array{id: int, visitNumber: string, occurredAt: string, status: array{value: string, label: string}, nextStep: string}, clearance: array{clearanceNumber: string, grantedAt: string}, patient: array{id: int, patientNumber: string, name: string}}
     */
    private function checkInData(VisitCheckIn $visitCheckIn): array
    {
        $eligibleVisitData = $this->eligibleVisitData($visitCheckIn->visit);

        return [
            'id' => $visitCheckIn->id,
            'checkInNumber' => $visitCheckIn->check_in_number,
            'checkedInAt' => $visitCheckIn->checked_in_at->toIso8601String(),
            'checkedInBy' => $visitCheckIn->checkedInBy->name,
            'visit' => [
                'id' => $eligibleVisitData['id'],
                'visitNumber' => $eligibleVisitData['visitNumber'],
                'occurredAt' => $eligibleVisitData['occurredAt'],
                'status' => $eligibleVisitData['status'],
                'nextStep' => $eligibleVisitData['nextStep'],
            ],
            'clearance' => $eligibleVisitData['clearance'],
            'patient' => $eligibleVisitData['patient'],
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
