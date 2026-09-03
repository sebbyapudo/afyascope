<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Consultations\BeginConsultation;
use App\AuditAction;
use App\BillStatus;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\ProcedureBillingHandoff;
use App\Models\User;
use App\Models\VisitCheckIn;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('begins one Consultation for an eligible Visit and records exactly one audit', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $visit = VisitCheckIn::factory()->create()->visit->fresh(['checkIn', 'consultation']);
    $patientId = $visit->patient_id;

    expect($visit->workflowMessage())->toBe('Ready for Doctor consultation');

    $consultation = app(BeginConsultation::class)->handle($doctor, $visit);

    expect($consultation->consultation_number)->toMatch('/^CON-\d{6,}$/')
        ->and($consultation->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->started_at)->not->toBeNull()
        ->and($consultation->finalized_at)->toBeNull()
        ->and($consultation->doctor->is($doctor))->toBeTrue()
        ->and($consultation->visit->is($visit))->toBeTrue()
        ->and($visit->fresh()->patient_id)->toBe($patientId)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Consultation in progress')
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->action)->toBe(AuditAction::ConsultationStarted)
        ->and($auditLog->actor->is($doctor))->toBeTrue()
        ->and($auditLog->subject->is($consultation))->toBeTrue()
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveCount(4)
        ->and($auditLog->after_values)->toHaveKey('consultation_number', $consultation->consultation_number)
        ->and($auditLog->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($auditLog->after_values)->toHaveKey('visit_number', $visit->visit_number)
        ->and($auditLog->after_values)->toHaveKey('doctor_user_id', $doctor->id);
});

it('rejects Visits that are not checked in or lack an actual check-in', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $financialClearance = FinancialClearance::factory()->create();
    $createdVisit = $financialClearance->bill->visit;

    expect(fn () => app(BeginConsultation::class)->handle(
        $doctor,
        $createdVisit,
    ))->toThrow(ValidationException::class);

    DB::table('visits')->where('id', $createdVisit->id)->update([
        'status' => VisitStatus::CheckedIn->value,
    ]);

    expect(fn () => app(BeginConsultation::class)->handle(
        $doctor,
        $createdVisit->fresh(),
    ))->toThrow(ValidationException::class);

    expect(Consultation::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('trusts the persisted VisitCheckIn as the authoritative clinical admission boundary', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $visit = VisitCheckIn::factory()->create()->visit;
    $bill = Bill::query()->where('visit_id', $visit->id)->sole();

    DB::table('bills')->where('id', $bill->id)->update([
        'status' => BillStatus::Open->value,
    ]);

    $consultation = app(BeginConsultation::class)->handle($doctor, $visit);

    expect($consultation->visit->is($visit))->toBeTrue()
        ->and($consultation->doctor->is($doctor))->toBeTrue()
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Consultation in progress')
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationStarted)->count())->toBe(1)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});

it('serializes competing Doctors so only the first start and audit win', function () {
    $firstDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $secondDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $visit = VisitCheckIn::factory()->create()->visit;

    $winner = app(BeginConsultation::class)->handle($firstDoctor, $visit);

    expect(fn () => app(BeginConsultation::class)->handle(
        $secondDoctor,
        $visit,
    ))->toThrow(ValidationException::class);

    expect(Consultation::query()->count())->toBe(1)
        ->and($visit->fresh()->consultation->is($winner))->toBeTrue()
        ->and($winner->doctor->is($firstDoctor))->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationStarted)->count())->toBe(1)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});

it('enforces Doctor authorization and revalidates active status inside the transaction', function () {
    $nonDoctor = User::factory()->forRole(StaffRole::Receptionist)->create();
    $inactiveDoctor = User::factory()->forRole(StaffRole::Doctor)->inactive()->create();
    $staleDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $visits = VisitCheckIn::factory()->count(3)->create()->map->visit;

    expect(fn () => app(BeginConsultation::class)->handle(
        $nonDoctor,
        $visits[0],
    ))->toThrow(AuthorizationException::class);

    expect(fn () => app(BeginConsultation::class)->handle(
        $inactiveDoctor,
        $visits[1],
    ))->toThrow(AuthorizationException::class);

    DB::table('users')->where('id', $staleDoctor->id)->update(['is_active' => false]);

    expect(fn () => app(BeginConsultation::class)->handle(
        $staleDoctor,
        $visits[2],
    ))->toThrow(ValidationException::class);

    expect(Consultation::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back the Consultation when audit recording fails', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $visit = VisitCheckIn::factory()->create()->visit;
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Consultation audit failed.'));

    expect(fn () => (new BeginConsultation($recordAuditLog))->handle(
        $doctor,
        $visit,
    ))->toThrow(RuntimeException::class, 'Consultation audit failed.');

    expect(Consultation::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Doctor consultation')
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});
