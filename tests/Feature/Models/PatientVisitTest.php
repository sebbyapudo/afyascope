<?php

use App\Models\Patient;
use App\Models\Visit;
use App\PatientSex;
use App\VisitStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('defines the Patient and Visit relationships', function () {
    $patient = Patient::factory()->create();
    $visits = Visit::factory()->count(2)->for($patient)->create();

    expect($patient->visits)->toHaveCount(2)
        ->and($patient->visits->modelKeys())->toBe($visits->modelKeys())
        ->and($visits->every(fn (Visit $visit): bool => $visit->patient->is($patient)))->toBeTrue();
});

it('generates Patient and Visit identifiers inside the model boundary', function () {
    $patient = Patient::factory()->create([
        'id' => 42,
        'patient_number' => 'PAT-SUPPLIED',
    ]);
    $visit = Visit::factory()->for($patient)->create([
        'id' => 73,
        'visit_number' => 'VIS-SUPPLIED',
    ]);

    expect($patient->patient_number)->toBe('PAT-000042')
        ->and($visit->visit_number)->toBe('VIS-000073')
        ->and($visit->status)->toBe(VisitStatus::Created);
});

it('produces unique sequential references during burst-style creation', function () {
    $patients = Patient::factory()->count(25)->create();
    $patient = $patients->firstOrFail();
    $visits = Visit::factory()->count(25)->for($patient)->create();

    expect($patients->pluck('patient_number')->unique())->toHaveCount(25)
        ->and($visits->pluck('visit_number')->unique())->toHaveCount(25);

    $patients->each(function (Patient $patient): void {
        expect($patient->patient_number)->toBe(
            'PAT-'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT),
        );
    });

    $visits->each(function (Visit $visit): void {
        expect($visit->visit_number)->toBe(
            'VIS-'.str_pad((string) $visit->id, 6, '0', STR_PAD_LEFT),
        );
    });
});

it('prevents persisted Patient and Visit identifiers from being edited', function () {
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();

    $patient->patient_number = 'PAT-CHANGED';
    expect(fn () => $patient->save())->toThrow(LogicException::class);

    $visit->visit_number = 'VIS-CHANGED';
    expect(fn () => $visit->save())->toThrow(LogicException::class);

    expect($patient->fresh()->patient_number)->not->toBe('PAT-CHANGED')
        ->and($visit->fresh()->visit_number)->not->toBe('VIS-CHANGED');
});

it('enforces Patient-number uniqueness at the database boundary', function () {
    $patient = Patient::factory()->create();

    expect(fn () => DB::table('patients')->insert([
        'patient_number' => $patient->patient_number,
        'first_name' => 'Duplicate',
        'last_name' => 'Reference',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('enforces Visit-number uniqueness at the database boundary', function () {
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();

    expect(fn () => DB::table('visits')->insert([
        'patient_id' => $patient->id,
        'visit_number' => $visit->visit_number,
        'occurred_at' => now(),
        'status' => VisitStatus::Created->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a Visit that references an unknown Patient', function () {
    expect(fn () => DB::table('visits')->insert([
        'patient_id' => 999999,
        'visit_number' => 'VIS-999999',
        'occurred_at' => now(),
        'status' => VisitStatus::Created->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('restricts deleting a Patient when Visit history exists', function () {
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();

    expect(fn () => $patient->delete())->toThrow(QueryException::class);

    expect($patient->fresh())->not->toBeNull()
        ->and($visit->fresh())->not->toBeNull();
});

it('casts optional sex and date of birth without requiring either value', function () {
    $patient = Patient::factory()->create([
        'date_of_birth' => '1985-02-14',
        'sex' => PatientSex::Other,
    ]);
    $patientWithoutOptionalDetails = Patient::factory()->withoutOptionalDemographics()->create();

    expect($patient->date_of_birth?->toDateString())->toBe('1985-02-14')
        ->and($patient->sex)->toBe(PatientSex::Other)
        ->and($patientWithoutOptionalDetails->date_of_birth)->toBeNull()
        ->and($patientWithoutOptionalDetails->sex)->toBeNull();
});
