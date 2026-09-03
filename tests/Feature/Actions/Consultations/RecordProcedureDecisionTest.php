<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Consultations\RecordProcedureDecision;
use App\AuditAction;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\ProcedureDecisionOutcome;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('records a procedure-required decision and matching billing handoff atomically', function () {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor, [
        'presenting_complaint' => 'Persistent symptoms.',
        'assessment_impression' => 'Endoscopy is clinically indicated.',
    ]);
    $service = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Upper gastrointestinal endoscopy',
        'unit_price_minor' => 350_000,
    ]);

    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $service->id,
        'clinical_rationale' => '  Findings warrant direct visualization.  ',
        'confirmed' => true,
    ]);
    $handoff = $decision->procedureBillingHandoff;

    expect($decision->decision_number)->toMatch('/^PDC-\d{6,}$/')
        ->and($decision->consultation->is($consultation))->toBeTrue()
        ->and($decision->visit->is($consultation->visit))->toBeTrue()
        ->and($decision->doctor->is($doctor))->toBeTrue()
        ->and($decision->serviceCatalogItem->is($service))->toBeTrue()
        ->and($decision->outcome)->toBe(ProcedureDecisionOutcome::ProcedureRequired)
        ->and($decision->clinical_rationale)->toBe('Findings warrant direct visualization.')
        ->and($handoff)->toBeInstanceOf(ProcedureBillingHandoff::class)
        ->and($handoff->procedureDecision->is($decision))->toBeTrue()
        ->and($handoff->visit->is($consultation->visit))->toBeTrue()
        ->and($handoff->decidedBy->is($doctor))->toBeTrue()
        ->and($handoff->serviceCatalogItem->is($service))->toBeTrue()
        ->and($handoff->handoff_number)->toMatch('/^PBH-\d{6,}$/')
        ->and($handoff->decided_at->equalTo($decision->decided_at))->toBeTrue()
        ->and(ProcedureBillingHandoff::query()->awaitingBill()->sole()->is($handoff))->toBeTrue()
        ->and(Bill::query()->where('type', 'procedure')->count())->toBe(0)
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($consultation->visit->fresh()->workflowMessage())->toBe('Awaiting procedure billing')
        ->and($consultation->fresh()->presenting_complaint)->toBe('Persistent symptoms.')
        ->and($consultation->fresh()->assessment_impression)->toBe('Endoscopy is clinically indicated.');

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->actor->is($doctor))->toBeTrue()
        ->and($auditLog->subject->is($decision))->toBeTrue()
        ->and($auditLog->action)->toBe(AuditAction::ConsultationProcedureDecided)
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveCount(6)
        ->and($auditLog->after_values)->toMatchArray([
            'decision_number' => $decision->decision_number,
            'consultation_number' => $consultation->consultation_number,
            'visit_number' => $consultation->visit->visit_number,
            'doctor_user_id' => $doctor->id,
            'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
            'service_catalog_item_id' => $service->id,
        ])
        ->and($auditLog->after_values)->not->toHaveKey('clinical_rationale')
        ->and(json_encode($auditLog->after_values))->not->toContain('Findings warrant');
});

it('rejects a service on the no-procedure branch', function () {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor);
    $service = ServiceCatalogItem::factory()->procedure()->create();

    expect(fn () => app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => 'no_procedure',
        'service_catalog_item_id' => $service->id,
        'confirmed' => true,
    ]))->toThrow(ValidationException::class);

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects a finalized Consultation', function () {
    $doctor = procedureDecisionDoctor();
    $consultation = Consultation::factory()
        ->for($doctor, 'doctor')
        ->createFinalizedFixture();

    expect(fn () => app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => 'no_procedure',
        'confirmed' => true,
    ]))->toThrow(AuthorizationException::class);

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('records no-procedure without a service handoff bill or lifecycle transition', function () {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor);

    $decision = app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => ProcedureDecisionOutcome::NoProcedure->value,
        'clinical_rationale' => '',
        'confirmed' => 'yes',
    ]);

    expect($decision->outcome)->toBe(ProcedureDecisionOutcome::NoProcedure)
        ->and($decision->service_catalog_item_id)->toBeNull()
        ->and($decision->clinical_rationale)->toBeNull()
        ->and($decision->procedureBillingHandoff)->toBeNull()
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(Bill::query()->where('type', 'procedure')->count())->toBe(0)
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($consultation->visit->fresh()->workflowMessage())->toBe('No procedure required')
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationProcedureDecided)->count())->toBe(1);
});

it('requires an active procedure service and explicit confirmation', function (array $attributes) {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor);

    expect(fn () => app(RecordProcedureDecision::class)->handle(
        $doctor,
        $consultation,
        $attributes,
    ))->toThrow(ValidationException::class);

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'missing procedure service' => [[
        'outcome' => 'procedure_required',
        'confirmed' => true,
    ]],
    'missing confirmation' => [[
        'outcome' => 'no_procedure',
    ]],
]);

it('rejects consultation-category and inactive procedure services without residue', function (ServiceCatalogItem $service) {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor);
    expect(fn () => app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => 'procedure_required',
        'service_catalog_item_id' => $service->id,
        'confirmed' => true,
    ]))->toThrow(ValidationException::class);

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'consultation category' => fn () => ServiceCatalogItem::factory()->create(),
    'inactive procedure' => fn () => ServiceCatalogItem::factory()->procedure()->inactive()->create(),
]);

it('rejects duplicate decisions and leaves exactly one decision handoff and audit', function () {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor);
    $service = ServiceCatalogItem::factory()->procedure()->create();
    $attributes = [
        'outcome' => 'procedure_required',
        'service_catalog_item_id' => $service->id,
        'confirmed' => true,
    ];

    app(RecordProcedureDecision::class)->handle($doctor, $consultation, $attributes);

    expect(fn () => app(RecordProcedureDecision::class)->handle(
        $doctor,
        $consultation,
        $attributes,
    ))->toThrow(ValidationException::class);

    expect(ProcedureDecision::query()->where('visit_id', $consultation->visit_id)->count())->toBe(1)
        ->and(ProcedureBillingHandoff::query()->where('visit_id', $consultation->visit_id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationProcedureDecided)->count())->toBe(1);
});

it('rejects another Doctor and every non-Doctor at the action boundary', function (StaffRole $role) {
    $responsibleDoctor = procedureDecisionDoctor();
    $actor = User::factory()->forRole($role)->create();
    $consultation = procedureDecisionConsultation($responsibleDoctor);

    expect(fn () => app(RecordProcedureDecision::class)->handle($actor, $consultation, [
        'outcome' => 'no_procedure',
        'confirmed' => true,
    ]))->toThrow(AuthorizationException::class);

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    StaffRole::Doctor,
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('rejects inactive ownership and a missing authoritative check-in', function () {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor);
    DB::table('visit_check_ins')->where('visit_id', $consultation->visit_id)->delete();

    expect(fn () => app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => 'no_procedure',
        'confirmed' => true,
    ]))->toThrow(ValidationException::class);

    DB::table('users')->where('id', $doctor->id)->update(['is_active' => false]);
    $doctor->refresh();

    expect(fn () => app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
        'outcome' => 'no_procedure',
        'confirmed' => true,
    ]))->toThrow(AuthorizationException::class);

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects forged ownership timestamps references and price fields', function () {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor);
    $service = ServiceCatalogItem::factory()->procedure()->create();
    $forged = [
        'id' => 999_999,
        'consultation_id' => 999_999,
        'visit_id' => 999_999,
        'patient_id' => 999_999,
        'doctor_id' => 999_999,
        'doctor_user_id' => 999_999,
        'decision_number' => 'PDC-FORGED',
        'decided_at' => '2020-01-01 00:00:00',
        'procedure_billing_handoff_id' => 999_999,
        'handoff_number' => 'PBH-FORGED',
        'bill_id' => 999_999,
        'amount_minor' => 1,
        'unit_price_minor' => 1,
        'price' => 1,
    ];

    try {
        app(RecordProcedureDecision::class)->handle($doctor, $consultation, [
            ...$forged,
            'outcome' => 'procedure_required',
            'service_catalog_item_id' => $service->id,
            'confirmed' => true,
        ]);
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toEqualCanonicalizing(array_keys($forged));
    }

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back both durable records when audit recording fails', function () {
    $doctor = procedureDecisionDoctor();
    $consultation = procedureDecisionConsultation($doctor);
    $service = ServiceCatalogItem::factory()->procedure()->create();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Procedure decision audit failed.'));

    expect(fn () => (new RecordProcedureDecision($recordAuditLog))->handle($doctor, $consultation, [
        'outcome' => 'procedure_required',
        'service_catalog_item_id' => $service->id,
        'confirmed' => true,
    ]))->toThrow(RuntimeException::class, 'Procedure decision audit failed.');

    expect(ProcedureDecision::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($consultation->visit->fresh()->status)->toBe(VisitStatus::CheckedIn);
});

function procedureDecisionDoctor(): User
{
    return User::factory()->forRole(StaffRole::Doctor)->create();
}

/** @param array<string, mixed> $attributes */
function procedureDecisionConsultation(User $doctor, array $attributes = []): Consultation
{
    return Consultation::factory()
        ->for($doctor, 'doctor')
        ->create($attributes);
}
