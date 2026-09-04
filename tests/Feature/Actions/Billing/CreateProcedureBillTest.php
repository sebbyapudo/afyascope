<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\CreateProcedureBill;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ProcedureBillingHandoff;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('creates one open procedure Bill from a durable Doctor handoff with an audited price snapshot', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $service = ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Upper gastrointestinal endoscopy',
        'unit_price_minor' => 350_050,
    ]);
    $handoff = ProcedureBillingHandoff::factory()
        ->for($service, 'serviceCatalogItem')
        ->createAuthoritativeDecisionFixture();
    $visit = $handoff->visit;

    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);

    expect($bill->bill_number)->toMatch('/^BIL-\d{6,}$/')
        ->and($bill->visit->is($visit))->toBeTrue()
        ->and($bill->procedureBillingHandoff->is($handoff))->toBeTrue()
        ->and($bill->type)->toBe(BillType::Procedure)
        ->and($bill->status)->toBe(BillStatus::Open)
        ->and($bill->items)->toHaveCount(1)
        ->and($bill->items->sole()->description)->toBe('Upper gastrointestinal endoscopy')
        ->and($bill->items->sole()->amount_minor)->toBe(350_050)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting procedure payment');

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->actor->is($accountant))->toBeTrue()
        ->and($auditLog->subject->is($bill))->toBeTrue()
        ->and($auditLog->action)->toBe(AuditAction::BillCreated)
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveCount(7)
        ->and($auditLog->after_values)->toHaveKey('bill_number', $bill->bill_number)
        ->and($auditLog->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($auditLog->after_values)->toHaveKey('type', BillType::Procedure->value)
        ->and($auditLog->after_values)->toHaveKey('status', BillStatus::Open->value)
        ->and($auditLog->after_values)->toHaveKey('procedure_billing_handoff_id', $handoff->id)
        ->and($auditLog->after_values)->toHaveKey('service_catalog_item_id', $service->id)
        ->and($auditLog->after_values)->toHaveKey('amount_minor', 350_050);
});

it('rejects a duplicate procedure Bill without another item or audit event', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    app(CreateProcedureBill::class)->handle($accountant, $handoff);

    expect(fn () => app(CreateProcedureBill::class)->handle(
        $accountant,
        $handoff,
    ))->toThrow(ValidationException::class);

    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(1)
        ->and(BillItem::query()->whereHas('bill', fn ($query) => $query
            ->where('type', BillType::Procedure->value))->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::BillCreated)->count())->toBe(1);
});

it('rejects a missing handoff and an inactive selected service without financial residue', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $missingHandoff = new ProcedureBillingHandoff;

    expect(fn () => app(CreateProcedureBill::class)->handle(
        $accountant,
        $missingHandoff,
    ))->toThrow(ValidationException::class);

    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $handoff->serviceCatalogItem->update(['is_active' => false]);

    expect(fn () => app(CreateProcedureBill::class)->handle(
        $accountant,
        $handoff,
    ))->toThrow(ValidationException::class);

    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects a handoff that no longer matches its authoritative procedure decision', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $otherProcedure = ServiceCatalogItem::factory()->procedure()->create();

    DB::table('procedure_billing_handoffs')
        ->where('id', $handoff->id)
        ->update(['service_catalog_item_id' => $otherProcedure->id]);

    expect(fn () => app(CreateProcedureBill::class)->handle(
        $accountant,
        $handoff->fresh(),
    ))->toThrow(ValidationException::class);

    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('enforces Accountant authorization at the procedure Bill action boundary', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

    expect(fn () => app(CreateProcedureBill::class)->handle(
        $actor,
        $handoff,
    ))->toThrow(AuthorizationException::class);

    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('rejects an inactive Accountant at the procedure Bill action boundary', function () {
    $inactiveAccountant = User::factory()
        ->forRole(StaffRole::Accountant)
        ->inactive()
        ->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

    expect(fn () => app(CreateProcedureBill::class)->handle(
        $inactiveAccountant,
        $handoff,
    ))->toThrow(AuthorizationException::class);

    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back the procedure Bill and item when audit recording fails', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $billItemCount = BillItem::query()->count();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Procedure Bill audit failed.'));

    expect(fn () => (new CreateProcedureBill($recordAuditLog))->handle(
        $accountant,
        $handoff,
    ))->toThrow(RuntimeException::class, 'Procedure Bill audit failed.');

    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(BillItem::query()->count())->toBe($billItemCount)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($handoff->visit->fresh()->status)->toBe(VisitStatus::CheckedIn);
});
