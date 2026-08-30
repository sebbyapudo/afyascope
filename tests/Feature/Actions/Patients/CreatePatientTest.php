<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Patients\CreatePatient;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\PatientSex;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('creates a Patient with valid administrative demographics and records a safe audit event', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();

    $patient = app(CreatePatient::class)->handle($actor, patientAttributes());

    expect($patient->patient_number)->toMatch('/^PAT-\d{6,}$/')
        ->and($patient->first_name)->toBe('Amina')
        ->and($patient->middle_name)->toBe('Wanjiku')
        ->and($patient->last_name)->toBe('Kamau')
        ->and($patient->date_of_birth?->toDateString())->toBe('1990-04-12')
        ->and($patient->sex)->toBe(PatientSex::Female)
        ->and($patient->phone)->toBe('+254700000001')
        ->and($patient->email)->toBe('amina@example.com');

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->actor->is($actor))->toBeTrue()
        ->and($auditLog->subject->is($patient))->toBeTrue()
        ->and($auditLog->action)->toBe(AuditAction::PatientRegistered)
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toBe([
            'patient_number' => $patient->patient_number,
        ])
        ->and($auditLog->getRawOriginal('after_values'))->not->toContain($patient->phone)
        ->and($auditLog->getRawOriginal('after_values'))->not->toContain($patient->email);
});

it('allows all optional demographics and contact attributes to be null', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();

    $patient = app(CreatePatient::class)->handle($actor, [
        'first_name' => 'Omondi',
        'last_name' => 'Otieno',
    ]);

    expect($patient->middle_name)->toBeNull()
        ->and($patient->date_of_birth)->toBeNull()
        ->and($patient->sex)->toBeNull()
        ->and($patient->phone)->toBeNull()
        ->and($patient->email)->toBeNull()
        ->and($patient->address)->toBeNull();
});

it('does not treat phone or email as unique patient identity', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $attributes = patientAttributes([
        'phone' => '+254711111111',
        'email' => 'shared@example.com',
    ]);

    $firstPatient = app(CreatePatient::class)->handle($actor, $attributes);
    $secondPatient = app(CreatePatient::class)->handle($actor, $attributes);

    expect($firstPatient->patient_number)->not->toBe($secondPatient->patient_number)
        ->and(Patient::query()->where('phone', '+254711111111')->count())->toBe(2)
        ->and(Patient::query()->where('email', 'shared@example.com')->count())->toBe(2);
});

it('generates distinct server-side Patient numbers across multiple records', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();

    $patientNumbers = collect(range(1, 20))
        ->map(fn (int $sequence): string => app(CreatePatient::class)
            ->handle($actor, patientAttributes([
                'first_name' => "Patient {$sequence}",
                'email' => null,
                'phone' => null,
            ]))
            ->patient_number);

    expect($patientNumbers->unique())->toHaveCount(20)
        ->and(Patient::query()->count())->toBe(20);
});

it('rejects a Patient number supplied through normal creation input without a false audit event', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();

    expect(fn () => app(CreatePatient::class)->handle($actor, patientAttributes([
        'patient_number' => 'PAT-MANUAL',
    ])))->toThrow(ValidationException::class);

    expect(Patient::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects invalid Patient demographics without creating an audit event', function (array $invalidAttributes) {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();

    expect(fn () => app(CreatePatient::class)->handle(
        $actor,
        patientAttributes($invalidAttributes),
    ))->toThrow(ValidationException::class);

    expect(Patient::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'future date of birth' => [['date_of_birth' => now()->addDay()->toDateString()]],
    'unsupported sex value' => [['sex' => 'unspecified-value']],
    'invalid email' => [['email' => 'not-an-email']],
    'overlong phone' => [['phone' => str_repeat('1', 33)]],
    'missing first name' => [['first_name' => null]],
    'missing last name' => [['last_name' => null]],
]);

it('enforces Patient authorization at the action boundary', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();

    expect(fn () => app(CreatePatient::class)->handle(
        $accountant,
        patientAttributes(),
    ))->toThrow(AuthorizationException::class);

    expect(Patient::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back Patient creation when audit recording fails', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Audit write failed.'));

    expect(fn () => (new CreatePatient($recordAuditLog))->handle(
        $actor,
        patientAttributes(),
    ))->toThrow(RuntimeException::class, 'Audit write failed.');

    expect(Patient::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function patientAttributes(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
        'date_of_birth' => '1990-04-12',
        'sex' => PatientSex::Female->value,
        'phone' => '+254700000001',
        'email' => 'amina@example.com',
        'address' => 'Nairobi',
    ], $overrides);
}
