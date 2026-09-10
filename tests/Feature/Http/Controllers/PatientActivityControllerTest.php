<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Clinical\ResolveRecoveryEscalation;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\RecoveryEscalationResolution;
use App\StaffRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{checkIn: VisitCheckIn, consultation: Consultation, decision: ProcedureDecision, readiness: PreProcedureReadiness, procedure: ProcedureRecord, recovery: RecoveryEpisode, visit: Visit}
 */
function patientActivityDownstreamContext(User $doctor, User $nurse, User $receptionist): array
{
    $checkIn = VisitCheckIn::factory()
        ->for($receptionist, 'checkedInBy')
        ->create();
    $visit = $checkIn->visit()->firstOrFail();
    $consultation = Consultation::factory()
        ->for($visit)
        ->for($doctor, 'doctor')
        ->create();
    $decision = ProcedureDecision::factory()
        ->for($consultation)
        ->procedureRequired()
        ->createAuthoritativeDecisionFixture();
    $readiness = PreProcedureReadiness::factory()
        ->ready()
        ->createAuthoritativePreparationFixture($decision, $nurse);
    $procedure = ProcedureRecord::factory()
        ->completed()
        ->createAuthoritativeProcedureFixture($decision, $readiness, $doctor);
    $recovery = RecoveryEpisode::factory()
        ->createAuthoritativeRecoveryFixture($procedure, $nurse);

    return compact('checkIn', 'consultation', 'decision', 'readiness', 'procedure', 'recovery', 'visit');
}

function recordPatientActivity(User $actor, AuditAction $action, Model $subject): AuditLog
{
    return app(RecordAuditLog::class)->handle($actor, $action, $subject);
}

it('tracks a Receptionist check-in after the Visit advances without leaking restricted records', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $otherReceptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $context = patientActivityDownstreamContext($doctor, $nurse, $receptionist);

    recordPatientActivity($receptionist, AuditAction::VisitCheckedIn, $context['checkIn']);
    recordPatientActivity($otherReceptionist, AuditAction::VisitCreated, Visit::factory()->create());
    $auditCount = AuditLog::query()->count();

    $this->actingAs($receptionist)
        ->get(route('patient-activity.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('patient-activity/index')
            ->has('activities.data', 1)
            ->where('activities.data.0.patient.patientNumber', $context['visit']->patient->patient_number)
            ->where('activities.data.0.visit.visitNumber', $context['visit']->visit_number)
            ->where('activities.data.0.visit.currentStage', 'Recovery in progress')
            ->where('activities.data.0.activity.label', 'Visit checked in')
            ->where('activities.data.0.destination', [
                'type' => 'check_in',
                'id' => $context['checkIn']->id,
            ])
            ->missing('activities.data.0.consultation')
            ->missing('activities.data.0.bill')
            ->missing('activities.data.0.payment')
            ->missing('activities.data.0.recovery')
            ->missing('activities.data.0.audit'));

    expect(AuditLog::query()->count())->toBe($auditCount);
});

it('tracks an Accountant financial action after the Visit advances without clinical or Nursing leakage', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $otherAccountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $context = patientActivityDownstreamContext($doctor, $nurse, $receptionist);
    $bill = Bill::query()->where('visit_id', $context['visit']->id)->sole();

    recordPatientActivity($accountant, AuditAction::BillCreated, $bill);
    recordPatientActivity($otherAccountant, AuditAction::BillCreated, Bill::factory()->create());

    $this->actingAs($accountant)
        ->get(route('patient-activity.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.visitNumber', $context['visit']->visit_number)
            ->where('activities.data.0.visit.currentStage', 'Recovery in progress')
            ->where('activities.data.0.activity.label', 'Bill created')
            ->where('activities.data.0.destination', [
                'type' => 'bill',
                'id' => $bill->id,
            ])
            ->missing('activities.data.0.consultation')
            ->missing('activities.data.0.procedure')
            ->missing('activities.data.0.readiness')
            ->missing('activities.data.0.recovery'));
});

it('keeps a Doctors completed procedure trackable and out of active procedure queues', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $context = patientActivityDownstreamContext($doctor, $nurse, $receptionist);

    recordPatientActivity($doctor, AuditAction::ProcedureCompleted, $context['procedure']);
    recordPatientActivity($otherDoctor, AuditAction::ConsultationStarted, Consultation::factory()->create());

    $this->actingAs($doctor)
        ->get(route('patient-activity.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.currentStage', 'Recovery in progress')
            ->where('activities.data.0.activity.label', 'Procedure completed')
            ->where('activities.data.0.destination', [
                'type' => 'procedure',
                'id' => $context['procedure']->id,
            ])
            ->missing('activities.data.0.recovery.observations')
            ->missing('activities.data.0.nursingNote'));

    $this->actingAs($doctor)
        ->get(route('clinical.procedures.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('readyProcedures.data', fn ($items): bool => collect($items)
                ->where('visit.id', $context['visit']->id)->isEmpty())
            ->where('inProgressProcedures.data', fn ($items): bool => collect($items)
                ->where('id', $context['procedure']->id)->isEmpty()));

    expect(Gate::forUser($doctor)->allows('recovery.manage'))->toBeFalse();
});

it('tracks completed Nurse readiness after downstream progress without Doctor write authority', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $context = patientActivityDownstreamContext($doctor, $nurse, $receptionist);

    recordPatientActivity($nurse, AuditAction::NursingReadinessCompleted, $context['readiness']);
    $otherContext = patientActivityDownstreamContext(
        User::factory()->forRole(StaffRole::Doctor)->create(),
        $otherNurse,
        User::factory()->forRole(StaffRole::Receptionist)->create(),
    );
    recordPatientActivity($otherNurse, AuditAction::RecoveryStarted, $otherContext['recovery']);

    $this->actingAs($nurse)
        ->get(route('patient-activity.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.visitNumber', $context['visit']->visit_number)
            ->where('activities.data.0.visit.currentStage', 'Recovery in progress')
            ->where('activities.data.0.activity.label', 'Nursing readiness completed')
            ->where('activities.data.0.destination', [
                'type' => 'preparation',
                'id' => $context['readiness']->id,
            ])
            ->missing('activities.data.0.procedure.findings')
            ->missing('activities.data.0.consultation.assessment'));

    expect(Gate::forUser($nurse)->allows('procedures.manage'))->toBeFalse();
});

it('tracks Nurse recovery readiness and escalation without exposing clinical concern text', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $context = patientActivityDownstreamContext($doctor, $nurse, $receptionist);

    app(AssessRecoveryReadiness::class)->handle($nurse, $context['recovery'], [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => true,
        'assessment_note' => 'Sensitive readiness note.',
        'escalation_reason' => 'Sensitive clinical concern.',
    ]);

    $this->actingAs($nurse)
        ->get(route('patient-activity.index', ['activity' => 'recovery']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.currentStage', 'Doctor review required')
            ->where('activities.data.0.activity.label', 'Recovery escalated')
            ->where('activities.data.0.destination', [
                'type' => 'recovery',
                'id' => $context['recovery']->id,
            ])
            ->missing('activities.data.0.recovery.reason')
            ->missing('activities.data.0.assessmentNote'));
});

it('tracks Doctor recovery escalation resolution as role-scoped Patient activity', function () {
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $context = patientActivityDownstreamContext($doctor, $nurse, $receptionist);
    app(AssessRecoveryReadiness::class)->handle($nurse, $context['recovery'], [
        'criteria_met' => false,
        'clinical_concern_requires_escalation' => true,
        'escalation_reason' => 'Clinical review required.',
    ]);
    $escalation = RecoveryEscalation::query()->sole();

    app(ResolveRecoveryEscalation::class)->handle($doctor, $escalation, [
        'resolution' => RecoveryEscalationResolution::ContinueMonitoring->value,
    ]);

    $this->actingAs($doctor)
        ->get(route('patient-activity.index', ['activity' => 'recovery']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.currentStage', 'Recovery in progress')
            ->where('activities.data.0.activity.label', 'Recovery escalation resolved')
            ->where('activities.data.0.destination', [
                'type' => 'recovery',
                'id' => $context['recovery']->id,
            ]));
});

it('searches filters and independently paginates attributed activity newest first', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $visits = collect();

    foreach (range(1, 21) as $minute) {
        $this->travelTo(sprintf('2026-09-09 09:%02d:00', $minute));
        $patient = Patient::factory()->create([
            'first_name' => $minute === 11 ? 'Needle' : "Patient{$minute}",
            'last_name' => "Tracking{$minute}",
        ]);
        $visit = Visit::factory()->for($patient)->create();
        recordPatientActivity($receptionist, AuditAction::VisitCreated, $visit);
        $visits->push($visit);
    }

    $this->travelBack();

    $this->actingAs($receptionist)
        ->get(route('patient-activity.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 20)
            ->where('activities.data.0.visit.visitNumber', $visits->last()->visit_number)
            ->where('activities.pagination.currentPage', 1)
            ->where('activities.pagination.lastPage', 2)
            ->where('activities.pagination.pageName', 'activity_page')
            ->where('activities.pagination.perPage', 20)
            ->where('activities.pagination.total', 21));

    $this->actingAs($receptionist)
        ->get(route('patient-activity.index', ['activity_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.visitNumber', $visits->first()->visit_number)
            ->where('activities.pagination.currentPage', 2));

    $needle = $visits->get(10);

    $this->actingAs($receptionist)
        ->get(route('patient-activity.index', ['q' => 'Needle']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.visit.visitNumber', $needle->visit_number)
            ->where('filters.q', 'Needle'));

    $this->actingAs($receptionist)
        ->get(route('patient-activity.index', ['activity' => 'visit']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activities.pagination.total', 21)
            ->where('filters.activity', 'visit'));

    $this->actingAs($receptionist)
        ->get(route('patient-activity.index', ['activity' => 'payment']))
        ->assertSessionHasErrors('activity');
});

it('uses the latest matching activity deterministically for each Visit', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $appointment = Appointment::factory()->create();
    $visit = Visit::factory()
        ->for($appointment)
        ->for($appointment->patient)
        ->create();

    $this->travelTo('2026-09-09 10:00:00');
    recordPatientActivity($receptionist, AuditAction::AppointmentCreated, $appointment);
    $this->travelTo('2026-09-09 10:05:00');
    recordPatientActivity($receptionist, AuditAction::VisitCreated, $visit);
    $this->travelBack();

    $this->actingAs($receptionist)
        ->get(route('patient-activity.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.activity.label', 'Visit created'));

    $this->actingAs($receptionist)
        ->get(route('patient-activity.index', ['activity' => 'appointment']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)
            ->where('activities.data.0.activity.label', 'Appointment created')
            ->where('activities.data.0.destination.type', 'appointment'));
});

it('allows only active operational roles to access personal Patient activity', function (StaffRole $role, bool $allowed) {
    $user = User::factory()->forRole($role)->create();
    $response = $this->actingAs($user)->get(route('patient-activity.index'));

    if ($allowed) {
        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.capabilities.viewPatientActivity', true));

        return;
    }

    $response->assertForbidden();
})->with([
    'Receptionist' => [StaffRole::Receptionist, true],
    'Accountant' => [StaffRole::Accountant, true],
    'Doctor' => [StaffRole::Doctor, true],
    'Nurse' => [StaffRole::Nurse, true],
    'Administrator' => [StaffRole::Administrator, false],
    'Management' => [StaffRole::Management, false],
]);

it('redirects guests and inactive staff away from Patient activity', function () {
    $this->get(route('patient-activity.index'))->assertRedirect(route('login'));

    $inactiveReceptionist = User::factory()
        ->forRole(StaffRole::Receptionist)
        ->inactive()
        ->create();

    $this->actingAs($inactiveReceptionist)
        ->get(route('patient-activity.index'))
        ->assertRedirect(route('login'));
});
