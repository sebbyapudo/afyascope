<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Patients\UpdatePatient;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\PatientSex;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('updates controlled Patient demographics and records only material changes', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'sex' => PatientSex::Female,
        'phone' => '+254700000001',
        'email' => 'amina@example.com',
        'address' => 'Nairobi',
    ]);
    $patientNumber = $patient->patient_number;

    $updatedPatient = app(UpdatePatient::class)->handle($actor, $patient, [
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'sex' => PatientSex::Female->value,
        'phone' => '+254700000002',
        'email' => 'amina@example.com',
        'address' => 'Nairobi',
    ]);

    expect($updatedPatient->patient_number)->toBe($patientNumber)
        ->and($updatedPatient->middle_name)->toBe('Wanjiku')
        ->and($updatedPatient->phone)->toBe('+254700000002');

    $auditLog = AuditLog::query()->sole();
    expect($auditLog->action)->toBe(AuditAction::PatientUpdated)
        ->and($auditLog->actor->is($actor))->toBeTrue()
        ->and($auditLog->subject->is($patient))->toBeTrue()
        ->and($auditLog->before_values)->toHaveCount(2)
        ->and($auditLog->before_values)->toHaveKey('middle_name', null)
        ->and($auditLog->before_values)->toHaveKey('phone', '+254700000001')
        ->and($auditLog->after_values)->toHaveCount(2)
        ->and($auditLog->after_values)->toHaveKey('middle_name', 'Wanjiku')
        ->and($auditLog->after_values)->toHaveKey('phone', '+254700000002')
        ->and($auditLog->metadata)->toBe([
            'changed_fields' => ['middle_name', 'phone'],
        ]);
});

it('does not audit a no-op Patient update', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
        'date_of_birth' => null,
        'sex' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
    ]);

    app(UpdatePatient::class)->handle($actor, $patient, [
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
        'date_of_birth' => null,
        'sex' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
    ]);

    expect(AuditLog::query()->count())->toBe(0);
});

it('rejects immutable identifiers at the Patient update action boundary', function (array $identifier) {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->withoutOptionalDemographics()->create([
        'first_name' => 'Amina',
        'last_name' => 'Kamau',
    ]);

    expect(fn () => app(UpdatePatient::class)->handle($actor, $patient, [
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
        'date_of_birth' => null,
        'sex' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
        ...$identifier,
    ]))->toThrow(ValidationException::class);
})->with([
    'Patient reference' => [['patient_number' => 'PAT-999999']],
    'primary key' => [['id' => 999999]],
]);

it('enforces Patient update authorization at the action boundary', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $patient = Patient::factory()->withoutOptionalDemographics()->create();

    expect(fn () => app(UpdatePatient::class)->handle($administrator, $patient, [
        'first_name' => $patient->first_name,
        'middle_name' => null,
        'last_name' => $patient->last_name,
        'date_of_birth' => null,
        'sex' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
    ]))->toThrow(AuthorizationException::class);
});

it('rolls back Patient demographic changes when audit recording fails', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->withoutOptionalDemographics()->create([
        'first_name' => 'Before',
    ]);
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Audit write failed.'));

    expect(fn () => (new UpdatePatient($recordAuditLog))->handle($actor, $patient, [
        'first_name' => 'After',
        'middle_name' => null,
        'last_name' => $patient->last_name,
        'date_of_birth' => null,
        'sex' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
    ]))->toThrow(RuntimeException::class, 'Audit write failed.');

    expect($patient->fresh()->first_name)->toBe('Before')
        ->and(AuditLog::query()->count())->toBe(0);
});
