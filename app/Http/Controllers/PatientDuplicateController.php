<?php

namespace App\Http\Controllers;

use App\Actions\Patients\FindPossiblePatientDuplicates;
use App\Http\Requests\FindPatientDuplicatesRequest;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;

class PatientDuplicateController extends Controller
{
    /**
     * Return deterministic possible Patient matches for registration assistance.
     */
    public function __invoke(
        FindPatientDuplicatesRequest $request,
        FindPossiblePatientDuplicates $findPossiblePatientDuplicates,
    ): JsonResponse {
        $patients = $findPossiblePatientDuplicates->handle($request->matchingAttributes());

        return response()->json([
            'matches' => $patients->map(static fn (Patient $patient): array => [
                'id' => $patient->id,
                'patientNumber' => $patient->patient_number,
                'name' => collect([
                    $patient->first_name,
                    $patient->middle_name,
                    $patient->last_name,
                ])->filter()->implode(' '),
                'dateOfBirth' => $patient->date_of_birth?->toDateString(),
                'phone' => $patient->phone,
                'email' => $patient->email,
            ])->values(),
        ]);
    }
}
