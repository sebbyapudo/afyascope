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

class UpdatePatient
{
    /** @var list<string> */
    private const DEMOGRAPHIC_FIELDS = [
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'sex',
        'phone',
        'email',
        'address',
    ];

    /**
     * Create a new action instance.
     */
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, Patient $patient, array $attributes): Patient
    {
        Gate::forUser($actor)->authorize('update', $patient);

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

        return DB::transaction(function () use ($actor, $patient, $validated): Patient {
            $lockedPatient = Patient::query()
                ->whereKey($patient->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPatient->fill($validated);

            $changedFields = array_values(array_intersect(
                self::DEMOGRAPHIC_FIELDS,
                array_keys($lockedPatient->getDirty()),
            ));

            if ($changedFields === []) {
                return $lockedPatient;
            }

            $beforeValues = [];
            $afterValues = [];

            foreach ($changedFields as $field) {
                $beforeValues[$field] = $lockedPatient->getRawOriginal($field);
                $afterValues[$field] = $lockedPatient->getAttributes()[$field] ?? null;
            }

            $lockedPatient->save();

            $this->recordAuditLog->handle(
                actor: $actor,
                action: AuditAction::PatientUpdated,
                subject: $lockedPatient,
                beforeValues: $beforeValues,
                afterValues: $afterValues,
                metadata: ['changed_fields' => $changedFields],
            );

            return $lockedPatient->refresh();
        });
    }
}
