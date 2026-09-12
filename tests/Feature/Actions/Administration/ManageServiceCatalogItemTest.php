<?php

use App\Actions\Administration\CreateServiceCatalogItem;
use App\Actions\Administration\SetServiceCatalogItemActiveState;
use App\Actions\Administration\UpdateServiceCatalogItem;
use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ProcedureBillingHandoff;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\StaffRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('creates an active catalog service and safe audit event atomically', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();

    $service = app(CreateServiceCatalogItem::class)->handle($administrator, [
        'name' => 'Specialist consultation',
        'category' => BillType::Consultation->value,
        'unit_price_minor' => 175_050,
    ]);

    expect($service->name)->toBe('Specialist consultation')
        ->and($service->category)->toBe(BillType::Consultation)
        ->and($service->unit_price_minor)->toBe(175_050)
        ->and($service->is_active)->toBeTrue();

    $audit = AuditLog::query()->sole();
    expect($audit->actor->is($administrator))->toBeTrue()
        ->and($audit->subject->is($service))->toBeTrue()
        ->and($audit->action)->toBe(AuditAction::ServiceCreated)
        ->and($audit->before_values)->toBeNull()
        ->and($audit->after_values)->toMatchArray([
            'name' => 'Specialist consultation',
            'category' => 'consultation',
            'unit_price_minor' => 175_050,
            'is_active' => true,
        ]);
});

it('updates current catalog identity without rewriting historical Bill item snapshots', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create([
        'name' => 'Original consultation',
        'unit_price_minor' => 100_000,
    ]);
    $billItem = BillItem::factory()
        ->for(Bill::factory())
        ->for($service, 'serviceCatalogItem')
        ->create();

    $updated = app(UpdateServiceCatalogItem::class)->handle($administrator, $service, [
        'name' => 'Updated consultation',
        'category' => BillType::Consultation->value,
    ]);

    expect($updated->name)->toBe('Updated consultation')
        ->and($updated->unit_price_minor)->toBe(100_000)
        ->and($billItem->fresh()->description)->toBe('Original consultation')
        ->and($billItem->fresh()->amount_minor)->toBe(100_000);

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::ServiceUpdated)
        ->and($audit->before_values)->toBe([
            'name' => 'Original consultation',
        ])
        ->and($audit->after_values)->toBe([
            'name' => 'Updated consultation',
        ]);
});

it('locks category after historical use while keeping the referenced service readable', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create();
    $billItem = BillItem::factory()
        ->for(Bill::factory())
        ->for($service, 'serviceCatalogItem')
        ->create();

    expect(fn () => app(UpdateServiceCatalogItem::class)->handle($administrator, $service, [
        'name' => $service->name,
        'category' => BillType::Procedure->value,
    ]))->toThrow(ValidationException::class);

    expect($service->fresh()->category)->toBe(BillType::Consultation)
        ->and($billItem->fresh()->serviceCatalogItem->is($service))->toBeTrue()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('audits activation changes exactly once and ignores no-op state requests', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create();
    $action = app(SetServiceCatalogItemActiveState::class);

    $action->handle($administrator, $service, false);
    $action->handle($administrator, $service, false);
    $action->handle($administrator, $service, true);

    expect($service->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->pluck('action')->all())->toBe([
            AuditAction::ServiceDeactivated,
            AuditAction::ServiceActivated,
        ]);
});

it('keeps historical financial and clinical references readable after deactivation', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $consultationService = ServiceCatalogItem::factory()->create([
        'name' => 'Historical consultation',
        'unit_price_minor' => 100_000,
    ]);
    $billItem = BillItem::factory()
        ->for(Bill::factory())
        ->for($consultationService, 'serviceCatalogItem')
        ->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $procedureService = $handoff->serviceCatalogItem;

    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $consultationService, false);
    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $procedureService, false);

    expect($billItem->fresh()->description)->toBe('Historical consultation')
        ->and($billItem->fresh()->amount_minor)->toBe(100_000)
        ->and($billItem->fresh()->serviceCatalogItem->is($consultationService))->toBeTrue()
        ->and($handoff->fresh()->serviceCatalogItem->is($procedureService))->toBeTrue()
        ->and($handoff->fresh()->procedureDecision->serviceCatalogItem->is($procedureService))->toBeTrue()
        ->and($consultationService->fresh()->is_active)->toBeFalse()
        ->and($procedureService->fresh()->is_active)->toBeFalse();
});

it('does not audit a no-op current configuration update', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create();

    app(UpdateServiceCatalogItem::class)->handle($administrator, $service, [
        'name' => $service->name,
        'category' => $service->category->value,
    ]);

    expect(AuditLog::query()->count())->toBe(0);
});

it('enforces Administrator authorization at each catalog action boundary', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $service = ServiceCatalogItem::factory()->create();

    expect(fn () => app(CreateServiceCatalogItem::class)->handle($actor, [
        'name' => 'Forbidden service',
        'category' => BillType::Consultation->value,
        'unit_price_minor' => 10_000,
    ]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateServiceCatalogItem::class)->handle($actor, $service, [
            'name' => 'Forbidden update',
            'category' => $service->category->value,
        ]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetServiceCatalogItemActiveState::class)->handle(
            $actor,
            $service,
            false,
        ))->toThrow(AuthorizationException::class);

    expect(ServiceCatalogItem::query()->where('name', 'Forbidden service')->exists())->toBeFalse()
        ->and($service->fresh()->name)->not->toBe('Forbidden update')
        ->and($service->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Management,
]);

it('rolls back catalog creation when its audit write fails', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $recorder = Mockery::mock(RecordAuditLog::class);
    $recorder->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable.'));

    expect(fn () => (new CreateServiceCatalogItem($recorder))->handle($administrator, [
        'name' => 'Rolled back service',
        'category' => BillType::Consultation->value,
        'unit_price_minor' => 10_000,
    ]))->toThrow(RuntimeException::class, 'Audit unavailable.');

    expect(ServiceCatalogItem::query()->where('name', 'Rolled back service')->exists())->toBeFalse();
});
