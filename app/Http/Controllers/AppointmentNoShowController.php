<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\MarkAppointmentNoShow;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentNoShowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Appointment $appointment,
        MarkAppointmentNoShow $markAppointmentNoShow,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $noShowAppointment = $markAppointmentNoShow->handle($actor, $appointment);

        return redirect()->route('appointments.show', $noShowAppointment)->with(
            'status',
            "Appointment {$noShowAppointment->appointment_number} was marked as a no-show.",
        );
    }
}
