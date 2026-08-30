<?php

namespace App\Http\Controllers;

use App\Actions\Patients\CreatePatient;
use App\Actions\Patients\UpdatePatient;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use App\Models\User;
use App\PatientSex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    /**
     * Display the Patient registry.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $patients = Patient::query()
            ->select([
                'id',
                'patient_number',
                'first_name',
                'middle_name',
                'last_name',
                'date_of_birth',
                'sex',
                'phone',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $searchPrefix = addcslashes($search, '\\%_').'%';

                $query->where(function (Builder $searchQuery) use ($searchPrefix): void {
                    $searchQuery
                        ->where('patient_number', 'like', $searchPrefix)
                        ->orWhere('first_name', 'like', $searchPrefix)
                        ->orWhere('middle_name', 'like', $searchPrefix)
                        ->orWhere('last_name', 'like', $searchPrefix)
                        ->orWhere('phone', 'like', $searchPrefix);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('patients/index', [
            'patients' => [
                'data' => $patients->getCollection()
                    ->map(fn (Patient $patient): array => $this->patientSummaryData($patient))
                    ->values(),
                'pagination' => [
                    'currentPage' => $patients->currentPage(),
                    'from' => $patients->firstItem(),
                    'lastPage' => $patients->lastPage(),
                    'to' => $patients->lastItem(),
                    'total' => $patients->total(),
                ],
            ],
            'filters' => ['q' => $search],
        ]);
    }

    /**
     * Show the Patient registration form.
     */
    public function create(): Response
    {
        return Inertia::render('patients/create', [
            'sexOptions' => $this->sexOptions(),
        ]);
    }

    /**
     * Register a Patient.
     */
    public function store(StorePatientRequest $request, CreatePatient $createPatient): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $patient = $createPatient->handle($actor, $request->patientAttributes());

        return redirect()->route('patients.show', $patient)->with(
            'status',
            "Patient {$patient->patient_number} was registered.",
        );
    }

    /**
     * Display a Patient's administrative profile.
     */
    public function show(Request $request, Patient $patient): Response
    {
        $status = $request->session()->get('status');

        return Inertia::render('patients/show', [
            'patient' => $this->patientData($patient),
            'status' => is_string($status) ? $status : null,
        ]);
    }

    /**
     * Show the demographics edit form.
     */
    public function edit(Patient $patient): Response
    {
        return Inertia::render('patients/edit', [
            'patient' => $this->patientData($patient),
            'sexOptions' => $this->sexOptions(),
        ]);
    }

    /**
     * Update Patient demographics.
     */
    public function update(
        UpdatePatientRequest $request,
        Patient $patient,
        UpdatePatient $updatePatient,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $updatedPatient = $updatePatient->handle($actor, $patient, $request->patientAttributes());

        return redirect()->route('patients.show', $updatedPatient)->with(
            'status',
            "Patient {$updatedPatient->patient_number} was updated.",
        );
    }

    /**
     * @return array{id: int, patientNumber: string, name: string, dateOfBirth: string|null, sex: array{value: string, label: string}|null, phone: string|null}
     */
    private function patientSummaryData(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'patientNumber' => $patient->patient_number,
            'name' => $this->patientName($patient),
            'dateOfBirth' => $patient->date_of_birth?->toDateString(),
            'sex' => $patient->sex === null ? null : [
                'value' => $patient->sex->value,
                'label' => $patient->sex->displayName(),
            ],
            'phone' => $patient->phone,
        ];
    }

    /**
     * @return array{id: int, patientNumber: string, firstName: string, middleName: string|null, lastName: string, name: string, dateOfBirth: string|null, sex: array{value: string, label: string}|null, phone: string|null, email: string|null, address: string|null, createdAt: string|null}
     */
    private function patientData(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'patientNumber' => $patient->patient_number,
            'firstName' => $patient->first_name,
            'middleName' => $patient->middle_name,
            'lastName' => $patient->last_name,
            'name' => $this->patientName($patient),
            'dateOfBirth' => $patient->date_of_birth?->toDateString(),
            'sex' => $patient->sex === null ? null : [
                'value' => $patient->sex->value,
                'label' => $patient->sex->displayName(),
            ],
            'phone' => $patient->phone,
            'email' => $patient->email,
            'address' => $patient->address,
            'createdAt' => $patient->created_at?->toIso8601String(),
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

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sexOptions(): array
    {
        return array_map(static fn (PatientSex $sex): array => [
            'value' => $sex->value,
            'label' => $sex->displayName(),
        ], PatientSex::cases());
    }
}
