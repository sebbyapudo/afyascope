<?php

namespace App\Http\Controllers;

use App\Actions\Visits\CreateVisit;
use App\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentVisitController extends Controller
{
    public function create(Appointment $appointment): Response
    {
        $appointment->load([
            'patient:id,patient_number,first_name,middle_name,last_name',
            'visit:id,appointment_id,visit_number,status',
        ]);

        /** @var Patient $patient */
        $patient = $appointment->patient;
        $linkedVisit = $appointment->visit;

        return Inertia::render('visits/create', [
            'patient' => [
                'id' => $patient->id,
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
            'appointment' => [
                'id' => $appointment->id,
                'appointmentNumber' => $appointment->appointment_number,
                'scheduledAt' => $appointment->scheduled_at->toIso8601String(),
                'isScheduled' => $appointment->status === AppointmentStatus::Scheduled,
                'linkedVisit' => $linkedVisit instanceof Visit ? [
                    'id' => $linkedVisit->id,
                    'visitNumber' => $linkedVisit->visit_number,
                ] : null,
            ],
        ]);
    }

    public function store(
        Request $request,
        Appointment $appointment,
        CreateVisit $createVisit,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $visit = $createVisit->fromAppointment($actor, $appointment);

        return redirect()->route('visits.show', $visit)->with(
            'status',
            "Visit {$visit->visit_number} was created from appointment {$appointment->appointment_number}.",
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
