<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Consultations\UpdateConsultationAssessment;
use App\AsaClassification;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\User;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('updates only the approved clinical assessment fields and records a narrative-free audit', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $originalVisitId = $consultation->visit_id;
    $originalStartedAt = $consultation->started_at;

    $updated = app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'presenting_complaint' => '  Persistent dyspepsia  ',
        'relevant_history' => 'Previous upper GI symptoms.',
        'current_medications' => 'Omeprazole.',
        'allergies' => 'No known drug allergies.',
        'examination_findings' => 'Stable clinical findings.',
        'asa_classification' => AsaClassification::Two->value,
        'assessment_impression' => 'Upper GI symptoms for assessment.',
        'plan_notes' => 'Continue clinical evaluation.',
        'unapproved_field' => 'This must not be persisted.',
    ]);

    expect($updated->presenting_complaint)->toBe('Persistent dyspepsia')
        ->and($updated->relevant_history)->toBe('Previous upper GI symptoms.')
        ->and($updated->current_medications)->toBe('Omeprazole.')
        ->and($updated->allergies)->toBe('No known drug allergies.')
        ->and($updated->examination_findings)->toBe('Stable clinical findings.')
        ->and($updated->asa_classification)->toBe(AsaClassification::Two)
        ->and($updated->assessment_impression)->toBe('Upper GI symptoms for assessment.')
        ->and($updated->plan_notes)->toBe('Continue clinical evaluation.')
        ->and($updated->getAttribute('unapproved_field'))->toBeNull()
        ->and($updated->visit_id)->toBe($originalVisitId)
        ->and($updated->doctor_user_id)->toBe($doctor->id)
        ->and($updated->status)->toBe(ConsultationStatus::InProgress)
        ->and($updated->started_at->equalTo($originalStartedAt))->toBeTrue()
        ->and($updated->finalized_at)->toBeNull()
        ->and($updated->visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($updated->visit->fresh()->workflowMessage())->toBe('Consultation in progress')
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->action)->toBe(AuditAction::ConsultationAssessmentUpdated)
        ->and($auditLog->actor->is($doctor))->toBeTrue()
        ->and($auditLog->subject->is($updated))->toBeTrue()
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveCount(3)
        ->and($auditLog->after_values)->toHaveKey('consultation_number', $updated->consultation_number)
        ->and($auditLog->after_values)->toHaveKey('visit_id', $updated->visit_id)
        ->and($auditLog->after_values)->toHaveKey('doctor_user_id', $doctor->id)
        ->and($auditLog->metadata)->toBe([
            'changed_fields' => [
                'presenting_complaint',
                'relevant_history',
                'current_medications',
                'allergies',
                'examination_findings',
                'asa_classification',
                'assessment_impression',
                'plan_notes',
            ],
        ])
        ->and(json_encode([
            $auditLog->before_values,
            $auditLog->after_values,
            $auditLog->metadata,
        ]))->not->toContain('Persistent dyspepsia');
});

it('normalizes optional empty fields to null and does not audit no-op updates', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();

    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'presenting_complaint' => 'Recorded complaint',
        'current_medications' => '   ',
    ]);

    expect($consultation->fresh()->current_medications)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(1);

    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation->fresh(), [
        'presenting_complaint' => '  Recorded complaint  ',
        'current_medications' => '',
    ]);

    expect(AuditLog::query()->count())->toBe(1);

    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation->fresh(), [
        'presenting_complaint' => '   ',
    ]);

    expect($consultation->fresh()->presenting_complaint)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(2);

    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation->fresh(), [
        'presenting_complaint' => null,
    ]);

    expect(AuditLog::query()->count())->toBe(2);
});

it('rejects invalid clinical values and forged lifecycle fields without writes or audits', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();

    expect(fn () => app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'asa_classification' => 'VII',
    ]))->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
            'presenting_complaint' => str_repeat('x', 5001),
        ]))->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
            'presenting_complaint' => ['not', 'text'],
        ]))->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
            'presenting_complaint' => 'Should roll back',
            'doctor_user_id' => User::factory()->forRole(StaffRole::Doctor)->create()->id,
            'status' => ConsultationStatus::Finalized->value,
            'finalized_at' => now()->toDateTimeString(),
        ]))->toThrow(ValidationException::class);

    $consultation->refresh();

    expect($consultation->presenting_complaint)->toBeNull()
        ->and($consultation->doctor_user_id)->toBe($doctor->id)
        ->and($consultation->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->finalized_at)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});

it('enforces responsible active Doctor ownership before and inside the transaction', function () {
    $responsibleDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $consultation = Consultation::factory()->for($responsibleDoctor, 'doctor')->create();

    expect(fn () => app(UpdateConsultationAssessment::class)->handle($otherDoctor, $consultation, [
        'assessment_impression' => 'Not authorized',
    ]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateConsultationAssessment::class)->handle($receptionist, $consultation, [
            'assessment_impression' => 'Not authorized',
        ]))->toThrow(AuthorizationException::class);

    DB::table('users')->where('id', $responsibleDoctor->id)->update(['is_active' => false]);

    expect(fn () => app(UpdateConsultationAssessment::class)->handle(
        $responsibleDoctor,
        $consultation,
        ['assessment_impression' => 'Stale actor attempt'],
    ))->toThrow(ValidationException::class);

    expect($consultation->fresh()->assessment_impression)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects assessment changes after Consultation finalization', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()
        ->for($doctor, 'doctor')
        ->createFinalizedFixture();

    expect(fn () => app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'plan_notes' => 'Too late',
    ]))->toThrow(AuthorizationException::class);

    expect($consultation->fresh()->plan_notes)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back assessment fields when audit recording fails', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Assessment audit failed.'));

    expect(fn () => (new UpdateConsultationAssessment($recordAuditLog))->handle(
        $doctor,
        $consultation,
        ['presenting_complaint' => 'Must roll back'],
    ))->toThrow(RuntimeException::class, 'Assessment audit failed.');

    expect($consultation->fresh()->presenting_complaint)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});
