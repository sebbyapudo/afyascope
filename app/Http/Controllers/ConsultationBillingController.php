<?php

namespace App\Http\Controllers;

use App\Actions\Billing\CreateConsultationBill;
use App\BillType;
use App\Http\Requests\StoreConsultationBillRequest;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationBillingController extends Controller
{
    public function index(): Response
    {
        $consultationServices = $this->activeConsultationServices();
        $visits = Visit::query()
            ->select(['id', 'patient_id', 'visit_number', 'occurred_at', 'status'])
            ->where('status', VisitStatus::Created->value)
            ->whereDoesntHave('bills', function (Builder $query): void {
                $query->where('type', BillType::Consultation->value);
            })
            ->with('patient:id,patient_number,first_name,middle_name,last_name')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->paginate(15);

        return Inertia::render('billing/consultations/index', [
            'consultationServices' => $consultationServices
                ->map(fn (ServiceCatalogItem $service): array => $this->serviceData($service))
                ->values(),
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
        ]);
    }

    public function create(Visit $visit): Response|RedirectResponse
    {
        $existingBill = $visit->consultationBill()->first();

        if ($existingBill instanceof Bill) {
            return redirect()->route('billing.bills.show', $existingBill);
        }

        $visit->load('patient:id,patient_number,first_name,middle_name,last_name');

        return Inertia::render('billing/consultations/create', [
            'visit' => $this->visitData($visit),
            'consultationServices' => $this->activeConsultationServices()
                ->map(fn (ServiceCatalogItem $service): array => $this->serviceData($service))
                ->values(),
        ]);
    }

    public function store(
        StoreConsultationBillRequest $request,
        Visit $visit,
        CreateConsultationBill $createConsultationBill,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $bill = $createConsultationBill->handle(
            $actor,
            $visit,
            $request->serviceCatalogItem(),
        );

        return redirect()->route('billing.bills.show', $bill)->with(
            'status',
            "Consultation Bill {$bill->bill_number} was created.",
        );
    }

    /**
     * @return Collection<int, ServiceCatalogItem>
     */
    private function activeConsultationServices(): Collection
    {
        return ServiceCatalogItem::query()
            ->where('category', BillType::Consultation->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'category', 'unit_price_minor', 'is_active']);
    }

    /**
     * @return array{id: int, name: string, unitPriceMinor: int}
     */
    private function serviceData(ServiceCatalogItem $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'unitPriceMinor' => $service->unit_price_minor,
        ];
    }

    /**
     * @return array{id: int, visitNumber: string, occurredAt: string, status: array{value: string, label: string}, patient: array{patientNumber: string, name: string}}
     */
    private function visitData(Visit $visit): array
    {
        /** @var Patient $patient */
        $patient = $visit->patient;

        return [
            'id' => $visit->id,
            'visitNumber' => $visit->visit_number,
            'occurredAt' => $visit->occurred_at->toIso8601String(),
            'status' => [
                'value' => $visit->status->value,
                'label' => $visit->status->displayName(),
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
