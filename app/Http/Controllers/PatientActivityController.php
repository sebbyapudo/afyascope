<?php

namespace App\Http\Controllers;

use App\Actions\PatientActivity\ListPatientActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientActivityController extends Controller
{
    public function __invoke(Request $request, ListPatientActivity $listPatientActivity): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'activity' => ['nullable', 'string', 'max:50'],
        ]);
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $search = trim((string) ($validated['q'] ?? ''));
        $activity = trim((string) ($validated['activity'] ?? ''));
        $patientActivity = $listPatientActivity->handle($actor, $search, $activity);

        return Inertia::render('patient-activity/index', [
            'activities' => [
                'data' => $patientActivity['data'],
                'pagination' => $patientActivity['pagination'],
            ],
            'filters' => [
                'q' => $search,
                'activity' => $activity,
            ],
            'activityOptions' => $patientActivity['activityOptions'],
        ]);
    }
}
