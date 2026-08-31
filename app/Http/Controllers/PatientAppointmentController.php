<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CreateAppointment;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PatientAppointmentController extends Controller
{
    public function create(Patient $patient): Response
    {
        return Inertia::render('appointments/create', [
            'patient' => [
                'id' => $patient->id,
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
        ]);
    }

    public function store(
        StoreAppointmentRequest $request,
        Patient $patient,
        CreateAppointment $createAppointment,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $appointment = $createAppointment->handle(
            $actor,
            $patient,
            $request->appointmentAttributes(),
        );

        return redirect()->route('appointments.show', $appointment)->with(
            'status',
            "Appointment {$appointment->appointment_number} was scheduled.",
        );
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
