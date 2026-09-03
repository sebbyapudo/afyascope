<?php

use App\Actions\Consultations\BeginConsultation;
use App\Actions\Consultations\UpdateConsultationAssessment;
use App\AsaClassification;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('queues eligible Visits oldest-ready first and shows only the Doctors own active work', function () {
    $doctor = clinicalControllerDoctor();

    $this->travelTo('2026-09-03 08:00:00');
    $oldestVisit = clinicalControllerReadyVisit();
    $this->travelTo('2026-09-03 09:00:00');
    $newestVisit = clinicalControllerReadyVisit();
    $this->travelTo('2026-09-03 10:00:00');
    $alreadyStartedVisit = clinicalControllerReadyVisit();
    Consultation::factory()->for($alreadyStartedVisit)->create();
    Visit::factory()->create();
    $ownConsultation = Consultation::factory()->for($doctor, 'doctor')->create();
    Consultation::factory()->create();
    $this->travelBack();

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinical/consultations/index')
            ->where('readyVisits.data', fn ($visits): bool => collect($visits)->pluck('id')->all() === [
                $oldestVisit->id,
                $newestVisit->id,
            ])
            ->where('readyVisits.pagination.total', 2)
            ->where('readyVisits.pagination.pageName', 'ready_page')
            ->where('readyVisits.data.0.nextStep', 'Ready for Doctor consultation')
            ->where('readyVisits.data.0.status', ['value' => 'checked_in', 'label' => 'Checked In'])
            ->where('inProgressConsultations.data', fn ($consultations): bool => collect($consultations)->pluck('id')->all() === [
                $ownConsultation->id,
            ])
            ->where('inProgressConsultations.pagination.total', 1)
            ->where('inProgressConsultations.pagination.pageName', 'in_progress_page')
            ->where('inProgressConsultations.data.0.canManage', true)
            ->where('auth.capabilities.viewConsultations', true)
            ->where('auth.capabilities.manageConsultations', true)
            ->missing('readyVisits.data.0.bill')
            ->missing('readyVisits.data.0.payment')
            ->missing('readyVisits.data.0.receipt')
            ->missing('readyVisits.data.0.financialClearance')
            ->missing('readyVisits.data.0.auditLogs')
            ->missing('readyVisits.data.0.clinicalNotes')
            ->missing('readyVisits.data.0.procedure')
        );
});

it('shows a sanitized checked-in Visit context before consultation starts', function () {
    $doctor = clinicalControllerDoctor();
    $visit = clinicalControllerReadyVisit();

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.create', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinical/consultations/create')
            ->where('visit.id', $visit->id)
            ->where('visit.visitNumber', $visit->visit_number)
            ->where('visit.patient.patientNumber', $visit->patient->patient_number)
            ->where('visit.status', ['value' => 'checked_in', 'label' => 'Checked In'])
            ->where('visit.nextStep', 'Ready for Doctor consultation')
            ->has('visit.checkIn.checkInNumber')
            ->has('visit.checkIn.checkedInAt')
            ->missing('visit.bill')
            ->missing('visit.payment')
            ->missing('visit.receipt')
            ->missing('visit.financialClearance')
            ->missing('visit.auditLogs')
            ->missing('visit.assessment')
            ->missing('visit.procedureBillingHandoff')
        );
});

it('begins consultation and redirects to the responsible Doctors workspace', function () {
    $doctor = clinicalControllerDoctor();
    $visit = clinicalControllerReadyVisit();

    $response = $this->actingAs($doctor)
        ->post(route('clinical.consultations.store', $visit));

    $consultation = Consultation::query()->sole();

    $response
        ->assertRedirect(route('clinical.consultations.show', $consultation))
        ->assertSessionHas(
            'status',
            "Consultation {$consultation->consultation_number} was started.",
        );

    expect($consultation->doctor->is($doctor))->toBeTrue()
        ->and($consultation->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->consultation_number)->toMatch('/^CON-\d{6,}$/')
        ->and($consultation->started_at)->not->toBeNull()
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Consultation in progress')
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationStarted)->count())->toBe(1)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('readyVisits.data', [])
            ->where('inProgressConsultations.data.0.id', $consultation->id)
        );
});

it('rejects every client-controlled consultation field', function () {
    $doctor = clinicalControllerDoctor();
    $visit = clinicalControllerReadyVisit();
    $payload = [
        'id' => 999_999,
        'visit_id' => 999_999,
        'patient_id' => 999_999,
        'doctor_user_id' => 999_999,
        'consultation_number' => 'CON-SUPPLIED',
        'status' => 'finalized',
        'started_at' => '2020-01-01 00:00:00',
        'finalized_at' => '2020-01-01 01:00:00',
        'check_in_id' => 999_999,
        'bill_id' => 999_999,
        'payment_id' => 999_999,
        'receipt_id' => 999_999,
        'financial_clearance_id' => 999_999,
        'procedure_billing_handoff_id' => 999_999,
    ];

    $this->actingAs($doctor)
        ->post(route('clinical.consultations.store', $visit), $payload)
        ->assertSessionHasErrors(array_keys($payload));

    expect(Consultation::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});

it('rejects stale duplicate and non-checked-in starts without an extra audit', function () {
    $doctor = clinicalControllerDoctor();
    $visit = clinicalControllerReadyVisit();
    $createdVisit = Visit::factory()->create();

    $this->actingAs($doctor)
        ->post(route('clinical.consultations.store', $createdVisit))
        ->assertSessionHasErrors('visit');

    $this->actingAs($doctor)
        ->post(route('clinical.consultations.store', $visit))
        ->assertRedirect();
    $this->actingAs($doctor)
        ->post(route('clinical.consultations.store', $visit))
        ->assertSessionHasErrors('visit');

    expect(Consultation::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationStarted)->count())->toBe(1)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});

it('shows the workspace to Doctors but only its responsible Doctor may manage it', function () {
    $responsibleDoctor = clinicalControllerDoctor();
    $otherDoctor = clinicalControllerDoctor();
    $consultation = Consultation::factory()
        ->for($responsibleDoctor, 'doctor')
        ->create();
    app(UpdateConsultationAssessment::class)->handle($responsibleDoctor, $consultation, [
        'presenting_complaint' => 'Intermittent upper abdominal symptoms.',
        'asa_classification' => AsaClassification::Two->value,
        'assessment_impression' => 'Assessment recorded.',
    ]);

    $this->actingAs($responsibleDoctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinical/consultations/show')
            ->where('consultation.consultationNumber', $consultation->consultation_number)
            ->where('consultation.canManage', true)
            ->has('consultation.doctor', fn (Assert $doctor) => $doctor
                ->where('id', $responsibleDoctor->id)
                ->where('name', $responsibleDoctor->name)
                ->missing('email')
                ->missing('role')
                ->missing('isActive'))
            ->where('consultation.assessment', [
                'presentingComplaint' => 'Intermittent upper abdominal symptoms.',
                'relevantHistory' => null,
                'currentMedications' => null,
                'allergies' => null,
                'examinationFindings' => null,
                'asaClassification' => 'II',
                'assessmentImpression' => 'Assessment recorded.',
                'planNotes' => null,
            ])
            ->where('asaClassifications', [
                ['value' => 'I', 'label' => 'ASA I'],
                ['value' => 'II', 'label' => 'ASA II'],
                ['value' => 'III', 'label' => 'ASA III'],
                ['value' => 'IV', 'label' => 'ASA IV'],
                ['value' => 'V', 'label' => 'ASA V'],
                ['value' => 'VI', 'label' => 'ASA VI'],
            ])
            ->where('consultation.visit.nextStep', 'Consultation in progress')
            ->missing('consultation.visit.bill')
            ->missing('consultation.visit.payment')
            ->missing('consultation.visit.receipt')
            ->missing('consultation.visit.auditLogs')
            ->missing('consultation.procedureBillingHandoff')
            ->missing('consultation.procedureDecision')
            ->missing('consultation.finalization')
        );

    $this->actingAs($otherDoctor)
        ->get(route('clinical.consultations.show', $consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.canManage', false)
            ->where(
                'consultation.assessment.presentingComplaint',
                'Intermittent upper abdominal symptoms.',
            )
        );

    $this->actingAs($otherDoctor)
        ->put(route('clinical.consultations.update', $consultation), [
            'presenting_complaint' => 'Unauthorized change',
        ])
        ->assertForbidden();

    expect(Gate::forUser($responsibleDoctor)->allows('update', $consultation))->toBeTrue()
        ->and(Gate::forUser($otherDoctor)->denies('update', $consultation))->toBeTrue();
});

it('lets the responsible Doctor save a validated assessment through the workspace', function () {
    $doctor = clinicalControllerDoctor();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();

    $response = $this->actingAs($doctor)
        ->put(route('clinical.consultations.update', $consultation), [
            'presenting_complaint' => '  Dyspepsia for three months  ',
            'relevant_history' => 'No previous endoscopy.',
            'current_medications' => '',
            'allergies' => 'None reported.',
            'examination_findings' => 'Clinically stable.',
            'asa_classification' => 'I',
            'assessment_impression' => 'Requires further assessment.',
            'plan_notes' => 'Proceed with the current consultation plan.',
        ]);

    $response
        ->assertRedirect(route('clinical.consultations.show', $consultation))
        ->assertSessionHas('status', 'Clinical assessment saved.');

    $consultation->refresh();

    expect($consultation->presenting_complaint)->toBe('Dyspepsia for three months')
        ->and($consultation->current_medications)->toBeNull()
        ->and($consultation->asa_classification)->toBe(AsaClassification::One)
        ->and($consultation->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationAssessmentUpdated)->count())->toBe(1);
});

it('rejects invalid and forged assessment updates without side effects', function () {
    $doctor = clinicalControllerDoctor();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    $forged = [
        'id' => 999_999,
        'visit_id' => 999_999,
        'patient_id' => 999_999,
        'doctor_user_id' => 999_999,
        'consultation_number' => 'CON-FORGED',
        'status' => 'finalized',
        'started_at' => '2020-01-01 00:00:00',
        'finalized_at' => '2020-01-01 01:00:00',
        'check_in_id' => 999_999,
        'bill_id' => 999_999,
        'payment_id' => 999_999,
        'receipt_id' => 999_999,
        'financial_clearance_id' => 999_999,
        'procedure_billing_handoff_id' => 999_999,
    ];

    $this->actingAs($doctor)
        ->put(route('clinical.consultations.update', $consultation), [
            'presenting_complaint' => str_repeat('x', 5001),
            'asa_classification' => 'VII',
        ])
        ->assertSessionHasErrors(['presenting_complaint', 'asa_classification']);

    $this->actingAs($doctor)
        ->put(route('clinical.consultations.update', $consultation), [
            ...$forged,
            'presenting_complaint' => 'Must not persist',
        ])
        ->assertSessionHasErrors(array_keys($forged));

    $consultation->refresh();

    expect($consultation->presenting_complaint)->toBeNull()
        ->and($consultation->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->doctor_user_id)->toBe($doctor->id)
        ->and($consultation->finalized_at)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});

it('does not audit a no-op assessment request', function () {
    $doctor = clinicalControllerDoctor();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'presenting_complaint' => 'Recorded complaint',
    ]);

    $this->actingAs($doctor)
        ->put(route('clinical.consultations.update', $consultation), [
            'presenting_complaint' => '  Recorded complaint  ',
        ])
        ->assertRedirect(route('clinical.consultations.show', $consultation));

    expect(AuditLog::query()->where('action', AuditAction::ConsultationAssessmentUpdated)->count())->toBe(1);
});

it('rejects assessment updates for finalized Consultations', function () {
    $doctor = clinicalControllerDoctor();
    $consultation = Consultation::factory()
        ->for($doctor, 'doctor')
        ->createFinalizedFixture();

    $this->actingAs($doctor)
        ->put(route('clinical.consultations.update', $consultation), [
            'assessment_impression' => 'Too late',
        ])
        ->assertForbidden();

    expect($consultation->fresh()->assessment_impression)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('projects consultation progress consistently without exposing the clinical record to Reception', function () {
    $doctor = clinicalControllerDoctor();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $visit = clinicalControllerReadyVisit();
    $patient = $visit->patient;

    app(BeginConsultation::class)->handle($doctor, $visit);

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.status', ['value' => 'checked_in', 'label' => 'Checked In'])
            ->where('visit.nextStep', 'Consultation in progress')
            ->missing('visit.consultation')
            ->missing('visit.doctor')
        );

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.status', ['value' => 'checked_in', 'label' => 'Checked In'])
            ->where('visitHistory.data.0.nextStep', 'Consultation in progress')
            ->missing('visitHistory.data.0.consultation')
            ->missing('visitHistory.data.0.doctor')
        );
});

it('redirects guests from every Doctor consultation endpoint', function () {
    $visit = clinicalControllerReadyVisit();
    $consultation = Consultation::factory()->create();

    $this->get(route('clinical.consultations.index'))->assertRedirect(route('login'));
    $this->get(route('clinical.consultations.create', $visit))->assertRedirect(route('login'));
    $this->post(route('clinical.consultations.store', $visit))->assertRedirect(route('login'));
    $this->get(route('clinical.consultations.show', $consultation))->assertRedirect(route('login'));
    $this->put(route('clinical.consultations.update', $consultation))->assertRedirect(route('login'));
});

it('denies every non-Doctor role from direct consultation URLs', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $visit = clinicalControllerReadyVisit();
    $consultation = Consultation::factory()->create();

    $this->actingAs($actor)->get(route('clinical.consultations.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('clinical.consultations.create', $visit))->assertForbidden();
    $this->actingAs($actor)->post(route('clinical.consultations.store', $visit))->assertForbidden();
    $this->actingAs($actor)->get(route('clinical.consultations.show', $consultation))->assertForbidden();
    $this->actingAs($actor)->put(route('clinical.consultations.update', $consultation))->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('logs out and denies an inactive Doctor', function () {
    $inactiveDoctor = User::factory()->forRole(StaffRole::Doctor)->inactive()->create();
    $visit = clinicalControllerReadyVisit();

    $this->actingAs($inactiveDoctor)
        ->post(route('clinical.consultations.store', $visit))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(Consultation::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('logs out an inactive responsible Doctor before an assessment update', function () {
    $doctor = clinicalControllerDoctor();
    $consultation = Consultation::factory()->for($doctor, 'doctor')->create();
    DB::table('users')->where('id', $doctor->id)->update(['is_active' => false]);
    $doctor->refresh();

    $this->actingAs($doctor)
        ->put(route('clinical.consultations.update', $consultation), [
            'presenting_complaint' => 'Must not persist',
        ])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect($consultation->fresh()->presenting_complaint)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('exposes the assessment update but no finalization or procedure endpoint', function () {
    expect(Route::has('clinical.consultations.index'))->toBeTrue()
        ->and(Route::has('clinical.consultations.create'))->toBeTrue()
        ->and(Route::has('clinical.consultations.store'))->toBeTrue()
        ->and(Route::has('clinical.consultations.show'))->toBeTrue()
        ->and(Route::has('clinical.consultations.update'))->toBeTrue()
        ->and(Route::has('clinical.consultations.finalize'))->toBeFalse()
        ->and(Route::has('clinical.assessments.store'))->toBeFalse()
        ->and(Route::has('procedures.store'))->toBeFalse();
});

function clinicalControllerDoctor(): User
{
    return User::factory()->forRole(StaffRole::Doctor)->create();
}

function clinicalControllerReadyVisit(): Visit
{
    return VisitCheckIn::factory()->create()->visit->fresh('patient');
}
