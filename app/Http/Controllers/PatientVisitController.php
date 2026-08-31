<?php

namespace App\Http\Controllers;

use App\Actions\Visits\CreateVisit;
use App\Http\Requests\StoreVisitRequest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PatientVisitController extends Controller
{
    public function create(Patient $patient): Response
    {
        return Inertia::render('visits/create', [
            'patient' => [
                'id' => $patient->id,
                'patientNumber' => $patient->patient_number,
                'name' => $this->patientName($patient),
            ],
        ]);
    }

    public function store(
        StoreVisitRequest $request,
        Patient $patient,
        CreateVisit $createVisit,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $visit = $createVisit->handle($actor, $patient);

        return redirect()->route('visits.show', $visit)->with(
            'status',
            "Visit {$visit->visit_number} was created.",
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
