<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CancelAppointment;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentCancelController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Appointment $appointment,
        CancelAppointment $cancelAppointment,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $cancelledAppointment = $cancelAppointment->handle($actor, $appointment);

        return redirect()->route('appointments.show', $cancelledAppointment)->with(
            'status',
            "Appointment {$cancelledAppointment->appointment_number} was cancelled.",
        );
    }
}
