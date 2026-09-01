<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\CreateConsultationBill;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('creates one open consultation Bill and audited service snapshot atomically', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create([
        'name' => 'Initial consultation',
        'unit_price_minor' => 125_050,
    ]);

    $bill = app(CreateConsultationBill::class)->handle($accountant, $visit, $service);

    expect($bill->bill_number)->toMatch('/^BIL-\d{6,}$/')
        ->and($bill->visit->is($visit))->toBeTrue()
        ->and($bill->type)->toBe(BillType::Consultation)
        ->and($bill->status)->toBe(BillStatus::Open)
        ->and($bill->items)->toHaveCount(1)
        ->and($bill->items->sole()->description)->toBe('Initial consultation')
        ->and($bill->items->sole()->amount_minor)->toBe(125_050)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Created)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting consultation payment');

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->actor->is($accountant))->toBeTrue()
        ->and($auditLog->subject->is($bill))->toBeTrue()
        ->and($auditLog->action)->toBe(AuditAction::BillCreated)
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveCount(6)
        ->and($auditLog->after_values)->toHaveKey('bill_number', $bill->bill_number)
        ->and($auditLog->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($auditLog->after_values)->toHaveKey('type', BillType::Consultation->value)
        ->and($auditLog->after_values)->toHaveKey('status', BillStatus::Open->value)
        ->and($auditLog->after_values)->toHaveKey('service_catalog_item_id', $service->id)
        ->and($auditLog->after_values)->toHaveKey('amount_minor', 125_050);
});

it('rejects a duplicate consultation Bill without another Bill item or audit event', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();
    app(CreateConsultationBill::class)->handle($accountant, $visit, $service);

    expect(fn () => app(CreateConsultationBill::class)->handle(
        $accountant,
        $visit,
        $service,
    ))->toThrow(ValidationException::class);

    expect(Bill::query()->count())->toBe(1)
        ->and(BillItem::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::BillCreated)->count())->toBe(1);
});

it('rejects a procedure-category service without writing financial or audit records', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $procedureService = ServiceCatalogItem::factory()->procedure()->create();

    expect(fn () => app(CreateConsultationBill::class)->handle(
        $accountant,
        $visit,
        $procedureService,
    ))->toThrow(ValidationException::class);

    expect(Bill::query()->count())->toBe(0)
        ->and(BillItem::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects an inactive consultation service without writing financial or audit records', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $inactiveService = ServiceCatalogItem::factory()->inactive()->create();

    expect(fn () => app(CreateConsultationBill::class)->handle(
        $accountant,
        $visit,
        $inactiveService,
    ))->toThrow(ValidationException::class);

    expect(Bill::query()->count())->toBe(0)
        ->and(BillItem::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('enforces Accountant authorization at the application-action boundary', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();

    expect(fn () => app(CreateConsultationBill::class)->handle(
        $receptionist,
        $visit,
        $service,
    ))->toThrow(AuthorizationException::class);

    expect(Bill::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back the Bill and item when audit recording fails', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Audit write failed.'));

    expect(fn () => (new CreateConsultationBill($recordAuditLog))->handle(
        $accountant,
        $visit,
        $service,
    ))->toThrow(RuntimeException::class, 'Audit write failed.');

    expect(Bill::query()->count())->toBe(0)
        ->and(BillItem::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Created);
});
