<?php

namespace App\Actions\Patients;

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\Patient;
use App\Models\User;
use App\PatientSex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreatePatient
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, array $attributes): Patient
    {
        Gate::forUser($actor)->authorize('create', Patient::class);

        $validated = Validator::make($attributes, [
            'id' => ['prohibited'],
            'patient_number' => ['prohibited'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', Rule::date()->todayOrBefore()],
            'sex' => ['nullable', Rule::enum(PatientSex::class)],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated): Patient {
            $patient = new Patient;
            $patient->first_name = $validated['first_name'];
            $patient->middle_name = $validated['middle_name'] ?? null;
            $patient->last_name = $validated['last_name'];
            $patient->date_of_birth = $validated['date_of_birth'] ?? null;
            $patient->sex = $validated['sex'] ?? null;
            $patient->phone = $validated['phone'] ?? null;
            $patient->email = $validated['email'] ?? null;
            $patient->address = $validated['address'] ?? null;
            $patient->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::PatientRegistered,
                subject: $patient,
                afterValues: [
                    'patient_number' => $patient->patient_number,
                ],
            );

            return $patient;
        });
    }
}
