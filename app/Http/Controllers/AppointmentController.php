<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\RescheduleAppointment;
use App\AppointmentStatus;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'awaiting_attendance' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $date = (string) ($validated['date'] ?? '');
        $status = (string) ($validated['status'] ?? '');
        $awaitingAttendance = (bool) ($validated['awaiting_attendance'] ?? false);
        $appointments = Appointment::query()
            ->select(['id', 'patient_id', 'appointment_number', 'scheduled_at', 'status'])
            ->with([
                'patient:id,patient_number,first_name,middle_name,last_name',
                'visit:id,appointment_id,visit_number,status',
                'visit.consultationBill:id,visit_id,type',
                'visit.consultationBill.payment:id,bill_id',
                'visit.consultationBill.financialClearance:id,bill_id',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $searchPrefix = addcslashes($search, '\\%_').'%';

                $query->where(function (Builder $searchQuery) use ($searchPrefix): void {
                    $searchQuery
                        ->where('appointment_number', 'like', $searchPrefix)
                        ->orWhereHas('patient', function (Builder $patientQuery) use ($searchPrefix): void {
                            $patientQuery
                                ->where('patient_number', 'like', $searchPrefix)
                                ->orWhere('first_name', 'like', $searchPrefix)
                                ->orWhere('middle_name', 'like', $searchPrefix)
                                ->orWhere('last_name', 'like', $searchPrefix);
                        });
                });
            })
            ->when($date !== '', function (Builder $query) use ($date): void {
                $filterDate = CarbonImmutable::parse($date);

                $query->whereBetween('scheduled_at', [
                    $filterDate->startOfDay(),
                    $filterDate->endOfDay(),
                ]);
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($awaitingAttendance, fn (Builder $query) => $query
                ->where('status', AppointmentStatus::Scheduled->value)
                ->whereDoesntHave('visit'))
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('appointments/index', [
            'appointments' => [
                'data' => $appointments->getCollection()
                    ->map(fn (Appointment $appointment): array => $this->appointmentData($appointment))
                    ->values(),
                'pagination' => [
                    'currentPage' => $appointments->currentPage(),
                    'from' => $appointments->firstItem(),
                    'lastPage' => $appointments->lastPage(),
                    'to' => $appointments->lastItem(),
                    'total' => $appointments->total(),
                ],
            ],
            'filters' => [
                'q' => $search,
                'date' => $date,
                'status' => $status,
                'awaitingAttendance' => $awaitingAttendance,
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function show(Request $request, Appointment $appointment): Response
    {
        $appointment->load([
            'patient:id,patient_number,first_name,middle_name,last_name',
            'visit:id,appointment_id,visit_number,status',
            'visit.consultationBill:id,visit_id,type',
            'visit.consultationBill.payment:id,bill_id',
            'visit.consultationBill.financialClearance:id,bill_id',
        ]);
        $status = $request->session()->get('status');

        return Inertia::render('appointments/show', [
            'appointment' => $this->appointmentData($appointment),
            'status' => is_string($status) ? $status : null,
        ]);
    }

    public function edit(Appointment $appointment): Response
    {
        $appointment->load([
            'patient:id,patient_number,first_name,middle_name,last_name',
            'visit:id,appointment_id,visit_number,status',
            'visit.consultationBill:id,visit_id,type',
            'visit.consultationBill.payment:id,bill_id',
            'visit.consultationBill.financialClearance:id,bill_id',
        ]);

        return Inertia::render('appointments/edit', [
            'appointment' => $this->appointmentData($appointment),
        ]);
    }

    public function update(
        RescheduleAppointmentRequest $request,
        Appointment $appointment,
        RescheduleAppointment $rescheduleAppointment,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $updatedAppointment = $rescheduleAppointment->handle(
            $actor,
            $appointment,
            $request->appointmentAttributes(),
        );

        return redirect()->route('appointments.show', $updatedAppointment)->with(
            'status',
            "Appointment {$updatedAppointment->appointment_number} schedule was saved.",
        );
    }

    /**
     * @return array{id: int, appointmentNumber: string, scheduledAt: string, status: array{value: string, label: string}, isScheduled: bool, linkedVisit: array{id: int, visitNumber: string, status: array{value: string, label: string}, nextStep: string}|null, patient: array{id: int, patientNumber: string, name: string}}
     */
    private function appointmentData(Appointment $appointment): array
    {
        /** @var Patient $patient */
        $patient = $appointment->patient;
        $linkedVisit = $appointment->visit;

        return [
            'id' => $appointment->id,
            'appointmentNumber' => $appointment->appointment_number,
            'scheduledAt' => $appointment->scheduled_at->toIso8601String(),
            'status' => [
                'value' => $appointment->status->value,
                'label' => $appointment->status->displayName(),
            ],
            'isScheduled' => $appointment->status === AppointmentStatus::Scheduled,
            'linkedVisit' => $linkedVisit instanceof Visit ? [
                'id' => $linkedVisit->id,
                'visitNumber' => $linkedVisit->visit_number,
                'status' => [
                    'value' => $linkedVisit->status->value,
                    'label' => $linkedVisit->status->displayName(),
                ],
                'nextStep' => $linkedVisit->workflowMessage(),
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

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(static fn (AppointmentStatus $status): array => [
            'value' => $status->value,
            'label' => $status->displayName(),
        ], AppointmentStatus::cases());
    }
}
