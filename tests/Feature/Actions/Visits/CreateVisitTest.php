<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Visits\CreateVisit;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('creates a Visit for an existing Patient and audits the operational event', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create();

    $visit = app(CreateVisit::class)->handle($actor, $patient, [
        'occurred_at' => '2026-08-30 09:15:00',
    ]);

    expect($visit->visit_number)->toMatch('/^VIS-\d{6,}$/')
        ->and($visit->patient->is($patient))->toBeTrue()
        ->and($visit->patient_id)->toBe($patient->id)
        ->and($visit->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-30 09:15:00')
        ->and($visit->status)->toBe(VisitStatus::Created);

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->actor->is($actor))->toBeTrue()
        ->and($auditLog->subject->is($visit))->toBeTrue()
        ->and($auditLog->action)->toBe(AuditAction::VisitCreated)
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveKey('visit_number', $visit->visit_number)
        ->and($auditLog->after_values)->toHaveKey('patient_id', $patient->id)
        ->and($auditLog->after_values)->toHaveKey('occurred_at', $visit->occurred_at->toIso8601String())
        ->and($auditLog->after_values)->toHaveKey('status', VisitStatus::Created->value)
        ->and($auditLog->after_values)->toHaveCount(4);
});

it('defaults a new Visit to the current occurrence time and Created state', function () {
    Carbon::withTestNow('2026-08-30 11:30:00', function (): void {
        $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
        $patient = Patient::factory()->create();

        $visit = app(CreateVisit::class)->handle($actor, $patient);

        expect($visit->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-30 11:30:00')
            ->and($visit->status)->toBe(VisitStatus::Created);
    });
});

it('creates multiple distinct Visits for one returning Patient without duplicating the Patient', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create();

    $firstVisit = app(CreateVisit::class)->handle($actor, $patient);
    $secondVisit = app(CreateVisit::class)->handle($actor, $patient, [
        'occurred_at' => now()->addDay()->toDateTimeString(),
    ]);

    expect($firstVisit->visit_number)->not->toBe($secondVisit->visit_number)
        ->and(Patient::query()->count())->toBe(1)
        ->and(Visit::query()->count())->toBe(2)
        ->and($patient->visits()->pluck('id')->all())->toBe([
            $firstVisit->id,
            $secondVisit->id,
        ]);
});

it('rejects controlled Visit values supplied through normal creation input', function (array $attributes) {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create();

    expect(fn () => app(CreateVisit::class)->handle(
        $actor,
        $patient,
        $attributes,
    ))->toThrow(ValidationException::class);

    expect(Visit::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'manual reference' => [['visit_number' => 'VIS-MANUAL']],
    'premature status' => [['status' => 'financially-cleared']],
    'primary key' => [['id' => 999999]],
    'Patient foreign key' => [['patient_id' => 999999]],
]);

it('rejects an unsaved Patient without creating a Visit or false audit event', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $unsavedPatient = Patient::factory()->make();

    expect(fn () => app(CreateVisit::class)->handle(
        $actor,
        $unsavedPatient,
    ))->toThrow(ValidationException::class);

    expect(Visit::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('enforces Visit authorization at the action boundary', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $patient = Patient::factory()->create();

    expect(fn () => app(CreateVisit::class)->handle(
        $doctor,
        $patient,
    ))->toThrow(AuthorizationException::class);

    expect(Visit::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('enforces both Appointment viewing and Visit creation at the handoff action boundary', function (StaffRole $staffRole) {
    $actor = User::factory()->forRole($staffRole)->create();
    $appointment = Appointment::factory()->create();

    expect(fn () => app(CreateVisit::class)->fromAppointment(
        $actor,
        $appointment,
    ))->toThrow(AuthorizationException::class);

    expect(Visit::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('rolls back Visit creation when audit recording fails', function () {
    $actor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Audit write failed.'));

    expect(fn () => (new CreateVisit($recordAuditLog))->handle(
        $actor,
        $patient,
    ))->toThrow(RuntimeException::class, 'Audit write failed.');

    expect(Visit::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($patient->fresh())->not->toBeNull();
});
