<?php

use App\Actions\Administration\CreateServiceCatalogItem;
use App\Actions\Administration\SetServiceCatalogItemActiveState;
use App\Actions\Administration\UpdateServiceCatalogItemPrice;
use App\Actions\Billing\CreateConsultationBill;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Consultations\RecordProcedureDecision;
use App\Actions\Patients\CreatePatient;
use App\Actions\Staff\UpdateStaffUser;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\ProcedureDecisionOutcome;
use App\StaffPermission;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('integrates the authoritative procedure catalog lifecycle with Doctor selection and history', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $service = app(CreateServiceCatalogItem::class)->handle($administrator, [
        'name' => 'Phase 6 colonoscopy',
        'category' => BillType::Procedure->value,
        'unit_price_minor' => 300_000,
    ]);
    $initialConsultation = Consultation::factory()->for($doctor, 'doctor')->create();

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.show', $initialConsultation))
        ->assertInertia(fn (Assert $page) => $page
            ->has('procedureServices', 1)
            ->where('procedureServices.0', [
                'id' => $service->id,
                'name' => 'Phase 6 colonoscopy',
            ]));

    $decision = app(RecordProcedureDecision::class)->handle($doctor, $initialConsultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $service->id,
        'clinical_rationale' => null,
        'confirmed' => true,
    ]);
    $historicalHandoff = $decision->procedureBillingHandoff()->sole();

    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $service, false);
    $laterConsultation = Consultation::factory()->for($doctor, 'doctor')->create();

    $this->actingAs($doctor)
        ->get(route('clinical.consultations.show', $laterConsultation))
        ->assertInertia(fn (Assert $page) => $page->has('procedureServices', 0));

    expect(fn () => app(RecordProcedureDecision::class)->handle($doctor, $laterConsultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $service->id,
        'clinical_rationale' => null,
        'confirmed' => true,
    ]))->toThrow(ValidationException::class)
        ->and($decision->fresh()->serviceCatalogItem->is($service))->toBeTrue()
        ->and($historicalHandoff->fresh()->serviceCatalogItem->is($service))->toBeTrue()
        ->and($service->fresh()->is_active)->toBeFalse();

    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $service, true);
    $laterDecision = app(RecordProcedureDecision::class)->handle($doctor, $laterConsultation, [
        'outcome' => ProcedureDecisionOutcome::ProcedureRequired->value,
        'service_catalog_item_id' => $service->id,
        'clinical_rationale' => null,
        'confirmed' => true,
    ]);

    expect($laterDecision->service_catalog_item_id)->toBe($service->id)
        ->and(ServiceCatalogItem::query()
            ->where('category', BillType::Procedure->value)
            ->where('name', 'Phase 6 colonoscopy')
            ->count())->toBe(1);
});

it('integrates repricing with immutable old Bills and updated future snapshots', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $consultationService = ServiceCatalogItem::factory()->create([
        'name' => 'Phase 6 consultation',
        'unit_price_minor' => 100_000,
    ]);
    $procedureService = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Phase 6 gastroscopy',
        'unit_price_minor' => 250_000,
    ]);

    $settledBill = app(CreateConsultationBill::class)->handle(
        $accountant,
        Visit::factory()->create(),
        $consultationService,
    );
    $payment = Payment::factory()->for($settledBill)->create();
    $receipt = Receipt::factory()->for($payment)->create();
    $settledBill->status = BillStatus::Paid;
    $settledBill->save();
    $clearance = FinancialClearance::factory()->for($settledBill)->create();
    $oldProcedureHandoff = ProcedureBillingHandoff::factory()
        ->for($procedureService, 'serviceCatalogItem')
        ->createAuthoritativeDecisionFixture();
    $openProcedureBill = app(CreateProcedureBill::class)->handle($accountant, $oldProcedureHandoff);

    $historicalAttributes = [
        'settled_bill' => $settledBill->fresh()->getAttributes(),
        'payment' => $payment->fresh()->getAttributes(),
        'receipt' => $receipt->fresh()->getAttributes(),
        'clearance' => $clearance->fresh()->getAttributes(),
        'open_procedure_bill' => $openProcedureBill->fresh()->getAttributes(),
    ];

    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $consultationService,
        125_000,
        100_000,
    );
    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $procedureService,
        300_000,
        250_000,
    );

    $newConsultationBill = app(CreateConsultationBill::class)->handle(
        $accountant,
        Visit::factory()->create(),
        $consultationService,
    );
    $newProcedureHandoff = ProcedureBillingHandoff::factory()
        ->for($procedureService, 'serviceCatalogItem')
        ->createAuthoritativeDecisionFixture();
    $newProcedureBill = app(CreateProcedureBill::class)->handle($accountant, $newProcedureHandoff);

    expect($settledBill->fresh()->getAttributes())->toBe($historicalAttributes['settled_bill'])
        ->and($payment->fresh()->getAttributes())->toBe($historicalAttributes['payment'])
        ->and($receipt->fresh()->getAttributes())->toBe($historicalAttributes['receipt'])
        ->and($clearance->fresh()->getAttributes())->toBe($historicalAttributes['clearance'])
        ->and($openProcedureBill->fresh()->getAttributes())->toBe($historicalAttributes['open_procedure_bill'])
        ->and($settledBill->fresh()->items->sole()->amount_minor)->toBe(100_000)
        ->and($openProcedureBill->fresh()->items->sole()->amount_minor)->toBe(250_000)
        ->and($newConsultationBill->items->sole()->amount_minor)->toBe(125_000)
        ->and($newProcedureBill->items->sole()->amount_minor)->toBe(300_000);

    $pendingVisit = Visit::factory()->create();
    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $consultationService, false);
    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $consultationService,
        140_000,
        125_000,
    );

    expect(fn () => app(CreateConsultationBill::class)->handle(
        $accountant,
        $pendingVisit,
        $consultationService,
    ))->toThrow(ValidationException::class);

    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $consultationService, true);
    $reactivatedBill = app(CreateConsultationBill::class)->handle(
        $accountant,
        $pendingVisit,
        $consultationService,
    );

    expect($reactivatedBill->items->sole()->amount_minor)->toBe(140_000);
});

it('integrates staff lifecycle changes with current authorization and preserved attribution', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create([
        'name' => 'Original Reception Actor',
    ]);
    $patient = app(CreatePatient::class)->handle($receptionist, [
        'first_name' => 'Phase',
        'middle_name' => null,
        'last_name' => 'Six',
        'date_of_birth' => null,
        'sex' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
    ]);
    $patientAudit = AuditLog::query()
        ->where('action', AuditAction::PatientRegistered)
        ->sole();

    app(UpdateStaffUser::class)->handle($administrator, $receptionist, [
        'name' => 'Renamed Historical Actor',
        'email' => $receptionist->email,
        'role' => StaffRole::Management->value,
        'is_active' => true,
    ]);

    expect($receptionist->fresh()->hasPermission(StaffPermission::PatientsCreate))->toBeFalse()
        ->and(fn () => app(CreatePatient::class)->handle($receptionist->fresh(), [
            'first_name' => 'Forbidden',
            'last_name' => 'Patient',
        ]))->toThrow(AuthorizationException::class)
        ->and($patientAudit->fresh()->actor_id)->toBe($receptionist->id)
        ->and($patientAudit->fresh()->subject_id)->toBe($patient->id)
        ->and($patientAudit->fresh()->actor->name)->toBe('Renamed Historical Actor');

    app(UpdateStaffUser::class)->handle($administrator, $receptionist->fresh(), [
        'name' => 'Renamed Historical Actor',
        'email' => $receptionist->email,
        'role' => StaffRole::Management->value,
        'is_active' => false,
    ]);

    $this->actingAs($receptionist->fresh())
        ->get(route('audit-logs.index'))
        ->assertRedirect(route('login'));

    expect($patientAudit->fresh()->actor_id)->toBe($receptionist->id)
        ->and($patientAudit->fresh()->action)->toBe(AuditAction::PatientRegistered)
        ->and(Role::query()->orderBy('slug')->pluck('slug')->all())->toBe(
            collect(StaffRole::cases())->map->value->sort()->values()->all(),
        );

    expect(fn () => app(UpdateStaffUser::class)->handle($administrator, $administrator, [
        'name' => $administrator->name,
        'email' => $administrator->email,
        'role' => StaffRole::Management->value,
        'is_active' => true,
    ]))->toThrow(ValidationException::class);
});

it('accepts the Phase 6 RBAC matrix and read-only sanitized audit review', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);

    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        125_000,
        100_000,
    );

    $priceAudit = AuditLog::query()
        ->where('action', AuditAction::ServicePriceUpdated)
        ->sole();
    $auditCount = AuditLog::query()->count();

    $this->actingAs($administrator)
        ->get(route('audit-logs.show', $priceAudit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditLog.actor.name', $administrator->name)
            ->where('auditLog.changes', [[
                'field' => 'unit_price_minor',
                'label' => 'Unit price (minor units)',
                'before' => 100_000,
                'after' => 125_000,
            ]])
            ->where('auditLog.metadata', [[
                'field' => 'currency',
                'label' => 'Currency',
                'value' => 'KES',
            ]])
            ->where('auth.capabilities.manageServiceCatalog', true)
            ->where('auth.capabilities.viewUsers', true)
            ->where('auth.capabilities.viewAudit', true));

    expect(AuditLog::query()->count())->toBe($auditCount)
        ->and(Gate::forUser($administrator)->allows(StaffPermission::ServicesManage))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows(StaffPermission::UsersManage))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows(StaffPermission::AuditView))->toBeTrue();

    $management = User::factory()->forRole(StaffRole::Management)->create();
    expect(Gate::forUser($management)->allows(StaffPermission::AuditView))->toBeTrue()
        ->and(Gate::forUser($management)->denies(StaffPermission::ServicesManage))->toBeTrue()
        ->and(Gate::forUser($management)->denies(StaffPermission::UsersManage))->toBeTrue();
    $this->actingAs($management)->get(route('audit-logs.show', $priceAudit))->assertOk();
    $this->actingAs($management)
        ->patch(route('service-catalog.price.update', $service), [
            'unit_price' => '1500.00',
            'current_unit_price_minor' => 125_000,
        ])
        ->assertForbidden();

    foreach ([
        StaffRole::Receptionist,
        StaffRole::Accountant,
        StaffRole::Doctor,
        StaffRole::Nurse,
    ] as $operationalRole) {
        $operationalUser = User::factory()->forRole($operationalRole)->create();

        expect(Gate::forUser($operationalUser)->denies(StaffPermission::ServicesManage))->toBeTrue()
            ->and(Gate::forUser($operationalUser)->denies(StaffPermission::UsersManage))->toBeTrue()
            ->and(Gate::forUser($operationalUser)->denies(StaffPermission::AuditView))->toBeTrue();

        $this->actingAs($operationalUser)
            ->get(route('audit-logs.show', $priceAudit))
            ->assertForbidden();
    }

    $inactiveAdministrator = User::factory()
        ->forRole(StaffRole::Administrator)
        ->inactive()
        ->create();
    $this->actingAs($inactiveAdministrator)
        ->get(route('audit-logs.show', $priceAudit))
        ->assertRedirect(route('login'));
    $this->get(route('audit-logs.show', $priceAudit))->assertRedirect(route('login'));

    expect(Route::has('audit-logs.store'))->toBeFalse()
        ->and(Route::has('audit-logs.update'))->toBeFalse()
        ->and(Route::has('audit-logs.destroy'))->toBeFalse()
        ->and(Route::has('staff.destroy'))->toBeFalse()
        ->and(Route::has('roles.store'))->toBeFalse()
        ->and(Route::has('roles.update'))->toBeFalse()
        ->and(Route::has('roles.destroy'))->toBeFalse();

    expect(function () use ($priceAudit): void {
        $priceAudit->action = AuditAction::ServiceCreated;
        $priceAudit->save();
    })
        ->toThrow(LogicException::class)
        ->and(fn () => $priceAudit->delete())
        ->toThrow(LogicException::class);
});
