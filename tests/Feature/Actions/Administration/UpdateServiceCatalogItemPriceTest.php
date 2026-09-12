<?php

use App\Actions\Administration\SetServiceCatalogItemActiveState;
use App\Actions\Administration\UpdateServiceCatalogItemPrice;
use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\CreateConsultationBill;
use App\Actions\Billing\CreateProcedureBill;
use App\AuditAction;
use App\BillStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('updates an active service price with one exact safe audit event', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 125_050]);

    $updated = app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        150_075,
        125_050,
    );

    expect($updated->unit_price_minor)->toBe(150_075)
        ->and($updated->name)->toBe($service->name)
        ->and($updated->category)->toBe($service->category)
        ->and($updated->is_active)->toBeTrue();

    $audit = AuditLog::query()->sole();
    expect($audit->actor->is($administrator))->toBeTrue()
        ->and($audit->subject->is($service))->toBeTrue()
        ->and($audit->action)->toBe(AuditAction::ServicePriceUpdated)
        ->and($audit->before_values)->toBe(['unit_price_minor' => 125_050])
        ->and($audit->after_values)->toBe(['unit_price_minor' => 150_075])
        ->and($audit->metadata)->toBe(['currency' => 'KES']);
});

it('does not write an audit event for a no-op price request', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 125_050]);

    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        125_050,
        125_050,
    );

    expect($service->fresh()->unit_price_minor)->toBe(125_050)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects non-positive prices at the application action boundary', function (int $priceMinor) {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 125_050]);

    expect(fn () => app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        $priceMinor,
        125_050,
    ))->toThrow(ValidationException::class);

    expect($service->fresh()->unit_price_minor)->toBe(125_050)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([0, -1]);

it('rejects a stale price update without overwriting or auditing the newer value', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);
    $service->update(['unit_price_minor' => 110_000]);

    expect(fn () => app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        125_000,
        100_000,
    ))->toThrow(ValidationException::class);

    expect($service->fresh()->unit_price_minor)->toBe(110_000)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('keeps an existing unpaid Bill item and total at their original snapshot', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create([
        'name' => 'Original consultation',
        'unit_price_minor' => 100_000,
    ]);
    $bill = Bill::factory()->create();
    $billItem = BillItem::factory()
        ->for($bill)
        ->for($service, 'serviceCatalogItem')
        ->create();

    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        175_050,
        100_000,
    );

    expect($bill->fresh()->status)->toBe(BillStatus::Open)
        ->and($bill->payment()->exists())->toBeFalse()
        ->and($billItem->fresh()->description)->toBe('Original consultation')
        ->and($billItem->fresh()->amount_minor)->toBe(100_000)
        ->and($bill->fresh()->totalAmountMinor())->toBe(100_000);
});

it('leaves settled financial records and completed Visits untouched', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);
    $bill = Bill::factory()->create();
    $billItem = BillItem::factory()
        ->for($bill)
        ->for($service, 'serviceCatalogItem')
        ->create();
    $payment = Payment::factory()->for($bill)->create();
    $receipt = Receipt::factory()->for($payment)->create();
    $bill->status = BillStatus::Paid;
    $bill->save();
    $clearance = FinancialClearance::factory()->for($bill)->create();
    $decision = ProcedureDecision::factory()->createAuthoritativeDecisionFixture();
    $completedVisit = $decision->visit;
    $completedVisit->completeFromClinicalWorkflow($decision, $decision->decided_at);

    $historicalAttributes = [
        'bill' => $bill->fresh()->getAttributes(),
        'bill_item' => $billItem->fresh()->getAttributes(),
        'payment' => $payment->fresh()->getAttributes(),
        'receipt' => $receipt->fresh()->getAttributes(),
        'clearance' => $clearance->fresh()->getAttributes(),
        'completed_visit' => $completedVisit->fresh()->getAttributes(),
    ];

    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        175_000,
        100_000,
    );

    expect($bill->fresh()->getAttributes())->toBe($historicalAttributes['bill'])
        ->and($billItem->fresh()->getAttributes())->toBe($historicalAttributes['bill_item'])
        ->and($payment->fresh()->getAttributes())->toBe($historicalAttributes['payment'])
        ->and($receipt->fresh()->getAttributes())->toBe($historicalAttributes['receipt'])
        ->and($clearance->fresh()->getAttributes())->toBe($historicalAttributes['clearance'])
        ->and($completedVisit->fresh()->getAttributes())->toBe($historicalAttributes['completed_visit'])
        ->and($completedVisit->status)->toBe(VisitStatus::Completed);
});

it('uses the updated current price for future consultation and procedure Bills', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $consultationService = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);
    $procedureService = ServiceCatalogItem::factory()->procedure()->create(['unit_price_minor' => 300_000]);
    $handoff = ProcedureBillingHandoff::factory()
        ->for($procedureService, 'serviceCatalogItem')
        ->createAuthoritativeDecisionFixture();

    $action = app(UpdateServiceCatalogItemPrice::class);
    $action->handle($administrator, $consultationService, 125_050, 100_000);
    $action->handle($administrator, $procedureService, 350_075, 300_000);

    $consultationBill = app(CreateConsultationBill::class)->handle(
        $accountant,
        Visit::factory()->create(),
        $consultationService,
    );
    $procedureBill = app(CreateProcedureBill::class)->handle($accountant, $handoff);

    expect($consultationBill->items->sole()->amount_minor)->toBe(125_050)
        ->and($procedureBill->items->sole()->amount_minor)->toBe(350_075)
        ->and($handoff->fresh()->matchesAuthoritativeDecision(
            $handoff->procedureDecision()->firstOrFail(),
        ))->toBeTrue();
});

it('allows inactive service pricing while availability remains independently enforced', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $service = ServiceCatalogItem::factory()->inactive()->create(['unit_price_minor' => 100_000]);

    app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        140_000,
        100_000,
    );

    expect($service->fresh()->unit_price_minor)->toBe(140_000)
        ->and($service->fresh()->is_active)->toBeFalse()
        ->and(fn () => app(CreateConsultationBill::class)->handle(
            $accountant,
            Visit::factory()->create(),
            $service,
        ))->toThrow(ValidationException::class);

    app(SetServiceCatalogItemActiveState::class)->handle($administrator, $service, true);
    $bill = app(CreateConsultationBill::class)->handle(
        $accountant,
        Visit::factory()->create(),
        $service,
    );

    expect($bill->items->sole()->amount_minor)->toBe(140_000);
});

it('enforces Administrator authorization at the price action boundary', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);

    expect(fn () => app(UpdateServiceCatalogItemPrice::class)->handle(
        $actor,
        $service,
        125_000,
        100_000,
    ))->toThrow(AuthorizationException::class);

    expect($service->fresh()->unit_price_minor)->toBe(100_000)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Accountant,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Management,
]);

it('denies inactive Administrators at the price action boundary', function () {
    $administrator = User::factory()
        ->forRole(StaffRole::Administrator)
        ->inactive()
        ->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);

    expect(fn () => app(UpdateServiceCatalogItemPrice::class)->handle(
        $administrator,
        $service,
        125_000,
        100_000,
    ))->toThrow(AuthorizationException::class);

    expect($service->fresh()->unit_price_minor)->toBe(100_000)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back the price change when its audit write fails', function () {
    $administrator = User::factory()->forRole(StaffRole::Administrator)->create();
    $service = ServiceCatalogItem::factory()->create(['unit_price_minor' => 100_000]);
    $recorder = Mockery::mock(RecordAuditLog::class);
    $recorder->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable.'));

    expect(fn () => (new UpdateServiceCatalogItemPrice($recorder))->handle(
        $administrator,
        $service,
        125_000,
        100_000,
    ))->toThrow(RuntimeException::class, 'Audit unavailable.');

    expect($service->fresh()->unit_price_minor)->toBe(100_000)
        ->and(AuditLog::query()->count())->toBe(0);
});
