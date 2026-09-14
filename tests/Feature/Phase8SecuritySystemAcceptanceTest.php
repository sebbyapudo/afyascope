<?php

use App\Actions\Billing\CreateConsultationBill;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantConsultationFinancialClearance;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordConsultationPayment;
use App\Actions\Billing\RecordProcedurePayment;
use App\Actions\Consultations\BeginConsultation;
use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Consultations\UpdateConsultationAssessment;
use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Actions\Nursing\CompletePreProcedureReadiness;
use App\Actions\Nursing\DischargeRecovery;
use App\Actions\Nursing\RecordRecoveryObservation;
use App\Actions\Nursing\StartPreProcedureReadiness;
use App\Actions\Nursing\StartRecoveryEpisode;
use App\Actions\Nursing\UpdatePreProcedureReadiness;
use App\Actions\Patients\CreatePatient;
use App\Actions\Procedures\CompleteProcedureRecord;
use App\Actions\Procedures\StartProcedureRecord;
use App\Actions\Procedures\UpdateProcedureRecordDocumentation;
use App\Actions\Visits\CheckInVisit;
use App\Actions\Visits\CreateVisit;
use App\AuditAction;
use App\BillType;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryObservation;
use App\Models\Role;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\PaymentMethod;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\RecoveryDischargeAccompanimentStatus;
use App\RecoveryDischargeDisposition;
use App\RecoveryEpisodeStatus;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('keeps the six fixed staff roles aligned with the canonical database permission mappings', function () {
    expect(collect(StaffRole::cases())->map->value->all())->toBe([
        'receptionist',
        'accountant',
        'doctor',
        'nurse',
        'administrator',
        'management',
    ])->and(Role::query()->count())->toBe(6);

    foreach (StaffRole::cases() as $staffRole) {
        $role = Role::query()->where('slug', $staffRole->value)->sole();
        $expectedPermissions = collect($staffRole->permissions())->map->value->sort()->values()->all();

        expect($role->name)->toBe($staffRole->displayName())
            ->and($role->permissions()->pluck('slug')->sort()->values()->all())->toBe($expectedPermissions);
    }

    expect((new User)->getFillable())->toBe(['name', 'email', 'password'])
        ->and((new User)->getHidden())->toContain('password', 'remember_token')
        ->and(Route::has('register'))->toBeFalse();
});

it('protects every application route and never exposes a business mutation over GET or DELETE', function () {
    $businessPrefixes = [
        'administration/', 'appointments', 'audit-logs', 'billing/', 'check-ins',
        'clinical/', 'dashboard', 'nursing/', 'patient-activity', 'patients',
        'reports/', 'staff', 'visits',
    ];

    $applicationRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => collect($businessPrefixes)
            ->contains(fn (string $prefix): bool => str_starts_with($route->uri(), $prefix)));

    expect($applicationRoutes)->not->toBeEmpty();

    $applicationRoutes->each(function (RoutingRoute $route): void {
        $middleware = $route->gatherMiddleware();

        expect($middleware)->toContain('web', 'auth')
            ->and(collect($middleware)->contains(
                fn (mixed $entry): bool => is_string($entry) && str_starts_with($entry, 'can:'),
            ))->toBeTrue("{$route->uri()} is missing policy middleware")
            ->and($route->methods())->not->toContain('DELETE');
    });

    foreach (phase8SecurityMutationRoutes() as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->toBeInstanceOf(RoutingRoute::class)
            ->and($route->methods())->not->toContain('GET', 'HEAD');
    }

    foreach (['staff.destroy', 'service-catalog.destroy', 'patients.destroy', 'visits.destroy'] as $routeName) {
        expect(Route::has($routeName))->toBeFalse();
    }
});

it('enforces representative direct URL boundaries for every fixed role', function () {
    $boundaries = [
        StaffRole::Receptionist->value => ['patients.index', 'billing.payments.index'],
        StaffRole::Accountant->value => ['billing.payments.index', 'patients.index'],
        StaffRole::Doctor->value => ['clinical.consultations.index', 'nursing.pre-procedure-readiness.index'],
        StaffRole::Nurse->value => ['nursing.pre-procedure-readiness.index', 'clinical.consultations.index'],
        StaffRole::Administrator->value => ['staff.index', 'patients.index'],
        StaffRole::Management->value => ['reports.management.index', 'staff.index'],
    ];

    foreach (StaffRole::cases() as $staffRole) {
        $actor = User::factory()->forRole($staffRole)->create();
        [$allowedRoute, $forbiddenRoute] = $boundaries[$staffRole->value];

        $this->actingAs($actor)->get(route($allowedRoute))->assertOk();
        $this->actingAs($actor)->get(route($forbiddenRoute))->assertForbidden();
    }
});

it('redirects guests and invalidates inactive sessions before protected data is projected', function () {
    foreach (StaffRole::cases() as $staffRole) {
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $inactive = User::factory()->forRole($staffRole)->inactive()->create();
        $this->actingAs($inactive)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
});

it('rejects cross-owner mutations and forged authoritative identifiers or money', function () {
    $ownerDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $otherDoctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()->for($ownerDoctor, 'doctor')->create();

    $this->actingAs($otherDoctor)
        ->put(route('clinical.consultations.update', $consultation), [
            'assessment_impression' => 'Attempted cross-owner edit.',
        ])
        ->assertForbidden();

    $decision = ProcedureDecision::factory()->procedureRequired()->createAuthoritativeDecisionFixture();
    $ownerNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $otherNurse = User::factory()->forRole(StaffRole::Nurse)->create();
    $readiness = PreProcedureReadiness::factory()
        ->createAuthoritativePreparationFixture($decision, $ownerNurse);

    $this->actingAs($otherNurse)
        ->put(route('nursing.pre-procedure-readiness.update', $readiness), [
            'consent_verified' => true,
            'patient_identity_verified' => true,
            'procedure_verified' => true,
            'allergies_reviewed' => true,
            'medications_reviewed' => true,
        ])
        ->assertForbidden();

    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $this->actingAs($receptionist)
        ->post(route('patients.store'), [
            'patient_number' => 'PAT-FORGED',
            'first_name' => 'Forged',
            'last_name' => 'Identifier',
        ])
        ->assertSessionHasErrors('patient_number');

    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = Bill::factory()->create();
    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_minor' => 1,
        ])
        ->assertSessionHasErrors('amount_minor');

    expect(Patient::query()->where('patient_number', 'PAT-FORGED')->exists())->toBeFalse()
        ->and(Payment::query()->where('bill_id', $bill->id)->exists())->toBeFalse()
        ->and($consultation->fresh()->doctor_user_id)->toBe($ownerDoctor->id)
        ->and($readiness->fresh()->nurse_user_id)->toBe($ownerNurse->id);
});

it('renders branded production errors without leaking exception details', function () {
    $this->app['env'] = 'production';
    config(['app.debug' => false]);

    Route::middleware('web')->get('/_phase8/expired', fn () => abort(419));
    Route::middleware('web')->get('/_phase8/failure', function (): never {
        throw new RuntimeException('phase8-secret-exception-detail');
    });

    $this->get('/_phase8/expired')
        ->assertStatus(419)
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 419));

    $this->get('/_phase8/failure')
        ->assertStatus(500)
        ->assertDontSee('phase8-secret-exception-detail')
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 500));
});

it('accepts the complete two-gate procedure journey with ownership concurrency and privacy intact', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    [$patient, $visit] = phase8CreateCheckedInVisit($receptionist, $accountant);

    $consultation = app(BeginConsultation::class)->handle($doctor, $visit);
    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'presenting_complaint' => 'Phase 8 private presenting complaint.',
        'relevant_history' => 'Phase 8 private history.',
        'assessment_impression' => 'Endoscopy is indicated.',
        'plan_notes' => 'Proceed through the second financial gate.',
    ]);
    $procedureService = ServiceCatalogItem::factory()->procedure()->create([
        'unit_price_minor' => 320_000,
    ]);
    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $procedureService->id,
        'clinical_rationale' => 'Phase 8 private clinical rationale.',
        'confirmed' => true,
    ]);

    $procedureBill = app(CreateProcedureBill::class)->handle(
        $accountant,
        $decision->procedureBillingHandoff,
    );
    app(RecordProcedurePayment::class)->handle($accountant, $procedureBill, PaymentMethod::MobileMoney);
    app(GrantProcedureFinancialClearance::class)->handle($accountant, $procedureBill);

    $readiness = app(StartPreProcedureReadiness::class)->handle($nurse, $visit);
    app(UpdatePreProcedureReadiness::class)->handle($nurse, $readiness, [
        'consent_verified' => true,
        'patient_identity_verified' => true,
        'procedure_verified' => true,
        'allergies_reviewed' => true,
        'medications_reviewed' => true,
        'observations' => 'Phase 8 private preparation observation.',
    ]);
    $readiness = app(CompletePreProcedureReadiness::class)->handle($nurse, $readiness);

    $procedure = app(StartProcedureRecord::class)->handle($doctor, $visit);
    $procedure = app(UpdateProcedureRecordDocumentation::class)->handle($doctor, $procedure, [
        'expected_lock_version' => $procedure->lock_version,
        'findings' => 'Phase 8 private findings.',
        'diagnosis_impression' => 'Phase 8 private impression.',
        'specimens_taken' => false,
        'specimen_notes' => null,
        'complications' => null,
        'outcome' => 'Completed safely.',
        'procedure_notes' => 'Phase 8 private procedure notes.',
    ]);
    $auditCountBeforeStaleWrite = AuditLog::query()->count();

    expect(fn () => app(UpdateProcedureRecordDocumentation::class)->handle($doctor, $procedure, [
        'expected_lock_version' => $procedure->lock_version - 1,
        'findings' => 'Stale overwrite.',
        'diagnosis_impression' => null,
        'specimens_taken' => false,
        'specimen_notes' => null,
        'complications' => null,
        'outcome' => null,
        'procedure_notes' => null,
    ]))->toThrow(ValidationException::class);

    expect(AuditLog::query()->count())->toBe($auditCountBeforeStaleWrite)
        ->and($procedure->fresh()->findings)->toBe('Phase 8 private findings.');

    $procedure = app(CompleteProcedureRecord::class)->handle($doctor, $procedure, $procedure->lock_version);
    $recovery = app(StartRecoveryEpisode::class)->handle($nurse, $procedure);
    app(RecordRecoveryObservation::class)->handle($nurse, $recovery, phase8RecoveryObservation());
    app(AssessRecoveryReadiness::class)->handle($nurse, $recovery, [
        'criteria_met' => true,
        'clinical_concern_requires_escalation' => false,
        'assessment_note' => 'Phase 8 private recovery assessment.',
    ]);
    $discharge = app(DischargeRecovery::class)->handle($nurse, $recovery->refresh(), phase8Discharge());

    expect($visit->fresh()->status)->toBe(VisitStatus::Completed)
        ->and($visit->fresh()->workflowMessage())->toBe('Discharged / Completed')
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::Finalized)
        ->and($procedure->status)->toBe(ProcedureRecordStatus::Completed)
        ->and($recovery->fresh()->status)->toBe(RecoveryEpisodeStatus::Completed)
        ->and($decision->doctor_user_id)->toBe($doctor->id)
        ->and($readiness->nurse_user_id)->toBe($nurse->id)
        ->and($procedure->doctor_user_id)->toBe($doctor->id)
        ->and($discharge->discharged_by_user_id)->toBe($nurse->id)
        ->and($procedureBill->items()->sole()->amount_minor)->toBe(320_000)
        ->and(Bill::query()->where('visit_id', $visit->id)->count())->toBe(2)
        ->and(Payment::query()->whereHas('bill', fn ($query) => $query->where('visit_id', $visit->id))->count())->toBe(2)
        ->and(FinancialClearance::query()->whereHas('bill', fn ($query) => $query->where('visit_id', $visit->id))->count())->toBe(2)
        ->and(ProcedureBillingHandoff::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(PreProcedureReadiness::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(ProcedureRecord::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(RecoveryEpisode::query()->where('visit_id', $visit->id)->count())->toBe(1)
        ->and(RecoveryDischarge::query()->where('recovery_episode_id', $recovery->id)->count())->toBe(1);

    foreach (phase8ProcedureJourneyAuditActions() as [$auditAction, $expectedCount]) {
        expect(AuditLog::query()->where('action', $auditAction)->count())->toBe($expectedCount);
    }

    $encodedAudits = json_encode(AuditLog::query()->get()->toArray(), JSON_THROW_ON_ERROR);
    expect($encodedAudits)
        ->not->toContain('Phase 8 private presenting complaint.')
        ->not->toContain('Phase 8 private clinical rationale.')
        ->not->toContain('Phase 8 private findings.')
        ->not->toContain('Phase 8 private recovery assessment.');

    $patientPayload = $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->inertiaProps();

    expect(json_encode($patientPayload, JSON_THROW_ON_ERROR))
        ->not->toContain('Phase 8 private presenting complaint.')
        ->not->toContain('Phase 8 private findings.')
        ->not->toContain('Phase 8 private recovery assessment.');

    $beforeReadOnlyRequests = phase8SecurityRecordCounts();
    $management = User::factory()->forRole(StaffRole::Management)->create();
    $reportPayload = $this->actingAs($management)
        ->get(route('reports.management.index'))
        ->assertOk()
        ->inertiaProps();
    $dashboardPayload = $this->actingAs($management)
        ->get(route('dashboard'))
        ->assertOk()
        ->inertiaProps();

    expect(phase8SecurityRecordCounts())->toBe($beforeReadOnlyRequests)
        ->and(json_encode([$reportPayload, $dashboardPayload], JSON_THROW_ON_ERROR))
        ->not->toContain($patient->patient_number)
        ->not->toContain($visit->visit_number)
        ->not->toContain('Phase 8 private findings.');
});

it('accepts the complete no-procedure journey without creating second-gate or downstream records', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $nurse = User::factory()->forRole(StaffRole::Nurse)->create();
    [, $visit] = phase8CreateCheckedInVisit($receptionist, $accountant);
    $consultation = app(BeginConsultation::class)->handle($doctor, $visit);
    app(UpdateConsultationAssessment::class)->handle($doctor, $consultation, [
        'assessment_impression' => 'No procedure is clinically indicated.',
    ]);
    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'clinical_rationale' => 'Phase 8 private conservative rationale.',
        'confirmed' => true,
    ]);
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(StartPreProcedureReadiness::class)->handle($nurse, $visit))
        ->toThrow(ValidationException::class);
    expect(fn () => app(StartProcedureRecord::class)->handle($doctor, $visit))
        ->toThrow(ValidationException::class);

    expect($decision->outcome)->toBe(ProcedureDecisionOutcome::NoProcedure)
        ->and($decision->service_catalog_item_id)->toBeNull()
        ->and($visit->fresh()->status)->toBe(VisitStatus::Completed)
        ->and($visit->fresh()->workflowMessage())->toBe('Consultation completed / Completed')
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::Finalized)
        ->and(ProcedureBillingHandoff::query()->where('visit_id', $visit->id)->exists())->toBeFalse()
        ->and(Bill::query()->where('visit_id', $visit->id)->where('type', BillType::Procedure)->exists())->toBeFalse()
        ->and(PreProcedureReadiness::query()->where('visit_id', $visit->id)->exists())->toBeFalse()
        ->and(ProcedureRecord::query()->where('visit_id', $visit->id)->exists())->toBeFalse()
        ->and(RecoveryEpisode::query()->where('visit_id', $visit->id)->exists())->toBeFalse()
        ->and(RecoveryObservation::query()->exists())->toBeFalse()
        ->and(RecoveryDischarge::query()->exists())->toBeFalse()
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationProcedureDecided)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::VisitCompleted)->count())->toBe(1)
        ->and(json_encode(AuditLog::query()->get()->toArray(), JSON_THROW_ON_ERROR))
        ->not->toContain('Phase 8 private conservative rationale.');
});

/** @return list<string> */
function phase8SecurityMutationRoutes(): array
{
    return [
        'service-catalog.store',
        'service-catalog.update',
        'service-catalog.price.update',
        'service-catalog.status.update',
        'appointments.update',
        'appointments.cancel',
        'appointments.no-show',
        'appointments.visit.store',
        'billing.clearances.store',
        'billing.payments.store',
        'billing.consultations.store',
        'billing.procedures.store',
        'clinical.consultations.store',
        'clinical.consultations.update',
        'clinical.consultations.procedure-decision.store',
        'clinical.procedures.store',
        'clinical.procedures.update',
        'clinical.procedures.complete',
        'clinical.recovery-escalations.update',
        'nursing.pre-procedure-readiness.store',
        'nursing.pre-procedure-readiness.update',
        'nursing.pre-procedure-readiness.complete',
        'nursing.recovery.store',
        'nursing.recovery.observations.store',
        'nursing.recovery.readiness.store',
        'nursing.recovery.discharge.store',
        'patients.store',
        'patients.update',
        'patients.possible-duplicates',
        'patients.appointments.store',
        'patients.visits.store',
        'staff.store',
        'staff.update',
        'check-ins.store',
    ];
}

/** @return array{0: Patient, 1: Visit, 2: VisitCheckIn} */
function phase8CreateCheckedInVisit(User $receptionist, User $accountant): array
{
    $patient = app(CreatePatient::class)->handle($receptionist, [
        'first_name' => 'Phase',
        'last_name' => 'Eight',
        'phone' => '+254700000008',
    ]);
    $visit = app(CreateVisit::class)->handle($receptionist, $patient);
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => 75_000,
    ]);
    $bill = app(CreateConsultationBill::class)->handle($accountant, $visit, $service);
    app(RecordConsultationPayment::class)->handle($accountant, $bill, PaymentMethod::Cash);
    app(GrantConsultationFinancialClearance::class)->handle($accountant, $bill);
    $checkIn = app(CheckInVisit::class)->handle($receptionist, $visit);

    return [$patient, $visit->refresh(), $checkIn];
}

/** @return array<string, mixed> */
function phase8RecoveryObservation(): array
{
    return [
        'general_recovery_status' => 'Phase 8 private recovery status.',
        'pain_score' => 1,
        'nausea' => false,
        'vomiting' => false,
        'systolic_blood_pressure' => 118,
        'diastolic_blood_pressure' => 76,
        'pulse_rate' => 72,
        'respiratory_rate' => 16,
        'oxygen_saturation' => 98,
        'supplemental_oxygen' => false,
        'nursing_note' => 'Phase 8 private recovery observation.',
    ];
}

/** @return array<string, mixed> */
function phase8Discharge(): array
{
    return [
        'condition_summary' => 'Stable after recovery.',
        'accompaniment_status' => RecoveryDischargeAccompanimentStatus::Accompanied->value,
        'disposition' => RecoveryDischargeDisposition::Home->value,
        'nursing_note' => 'Phase 8 private discharge note.',
        'general_care_instructions' => 'Rest for the remainder of today.',
        'activity_driving_instructions' => 'Do not drive today.',
        'diet_fluids_instructions' => 'Resume fluids as tolerated.',
        'medication_instructions' => null,
        'warning_signs_instructions' => 'Return for severe pain or bleeding.',
        'follow_up_instructions' => 'Follow up as advised.',
        'confirm_discharge' => true,
    ];
}

/** @return list<array{0: AuditAction, 1: int}> */
function phase8ProcedureJourneyAuditActions(): array
{
    return [
        [AuditAction::PatientRegistered, 1],
        [AuditAction::VisitCreated, 1],
        [AuditAction::BillCreated, 2],
        [AuditAction::PaymentRecorded, 2],
        [AuditAction::ReceiptIssued, 2],
        [AuditAction::ConsultationFinancialCleared, 1],
        [AuditAction::VisitCheckedIn, 1],
        [AuditAction::ConsultationStarted, 1],
        [AuditAction::ConsultationAssessmentUpdated, 1],
        [AuditAction::ConsultationProcedureDecided, 1],
        [AuditAction::ProcedureFinancialCleared, 1],
        [AuditAction::NursingPreparationStarted, 1],
        [AuditAction::NursingReadinessCompleted, 1],
        [AuditAction::ProcedureStarted, 1],
        [AuditAction::ProcedureDocumentationUpdated, 1],
        [AuditAction::ProcedureCompleted, 1],
        [AuditAction::RecoveryStarted, 1],
        [AuditAction::RecoveryObservationRecorded, 1],
        [AuditAction::RecoveryReadinessAssessed, 1],
        [AuditAction::RecoveryDischarged, 1],
        [AuditAction::VisitCompleted, 1],
    ];
}

/** @return array<string, int> */
function phase8SecurityRecordCounts(): array
{
    return [
        'audits' => AuditLog::query()->count(),
        'bills' => Bill::query()->count(),
        'payments' => Payment::query()->count(),
        'clearances' => FinancialClearance::query()->count(),
        'visits' => Visit::query()->count(),
    ];
}
