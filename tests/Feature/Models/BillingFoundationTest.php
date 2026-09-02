<?php

use App\BillStatus;
use App\BillType;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\ProcedureBillingHandoff;
use App\Models\ServiceCatalogItem;
use App\Models\Visit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('defines the Visit Bill and catalog relationships without duplicating Patient data', function () {
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();
    $bill = Bill::factory()->for($visit)->create();
    $service = ServiceCatalogItem::factory()->create([
        'name' => 'Initial consultation',
        'unit_price_minor' => 125_050,
    ]);
    $item = BillItem::factory()
        ->for($bill)
        ->for($service, 'serviceCatalogItem')
        ->create();

    expect($bill->visit->is($visit))->toBeTrue()
        ->and($visit->bills->modelKeys())->toBe([$bill->id])
        ->and($item->bill->is($bill))->toBeTrue()
        ->and($item->serviceCatalogItem->is($service))->toBeTrue()
        ->and($patient->visits)->toHaveCount(1)
        ->and(Schema::hasColumn('bills', 'patient_id'))->toBeFalse();
});

it('generates immutable sequential Bill references inside the model boundary', function () {
    $bill = Bill::factory()->create([
        'id' => 73,
        'bill_number' => 'BIL-SUPPLIED',
    ]);

    expect($bill->bill_number)->toBe('BIL-000073')
        ->and($bill->status)->toBe(BillStatus::Open);

    $bill->bill_number = 'BIL-CHANGED';

    expect(fn () => $bill->save())->toThrow(LogicException::class)
        ->and($bill->fresh()->bill_number)->toBe('BIL-000073');
});

it('produces unique Bill references during burst-style creation', function () {
    $bills = Bill::factory()->count(25)->create();

    expect($bills->pluck('bill_number')->unique())->toHaveCount(25);

    $bills->each(function (Bill $bill): void {
        expect($bill->bill_number)->toBe(
            'BIL-'.str_pad((string) $bill->id, 6, '0', STR_PAD_LEFT),
        );
    });
});

it('allows one Bill per financial gate for a Visit', function () {
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $visit = $handoff->visit;
    $consultationBill = $visit->consultationBill;
    $procedureBill = Bill::factory()->procedure($handoff)->create();

    expect($consultationBill->type)->toBe(BillType::Consultation)
        ->and($procedureBill->type)->toBe(BillType::Procedure)
        ->and($procedureBill->procedureBillingHandoff->is($handoff))->toBeTrue()
        ->and($visit->fresh()->bills)->toHaveCount(2);

    expect(fn () => Bill::factory()->for($visit)->create())
        ->toThrow(QueryException::class);

    expect(fn () => Bill::factory()->procedure($handoff)->create())
        ->toThrow(QueryException::class);
});

it('keeps a Bill bound to its original Visit and financial gate', function () {
    $bill = Bill::factory()->create();

    $bill->visit()->associate(Visit::factory()->create());
    expect(fn () => $bill->save())->toThrow(LogicException::class);

    $bill->refresh();
    $bill->type = BillType::Procedure;
    expect(fn () => $bill->save())->toThrow(LogicException::class);
});

it('stores positive integer minor units and immutable catalog snapshots', function () {
    $bill = Bill::factory()->create();
    $firstService = ServiceCatalogItem::factory()->create([
        'name' => 'Consultation assessment',
        'unit_price_minor' => 125_050,
    ]);
    $secondService = ServiceCatalogItem::factory()->create([
        'name' => 'Consultation review',
        'unit_price_minor' => 25_025,
    ]);
    $firstItem = BillItem::factory()
        ->for($bill)
        ->for($firstService, 'serviceCatalogItem')
        ->create([
            'description' => 'Client supplied description',
            'amount_minor' => 1,
        ]);
    BillItem::factory()
        ->for($bill)
        ->for($secondService, 'serviceCatalogItem')
        ->create();

    expect($firstItem->description)->toBe('Consultation assessment')
        ->and($firstItem->amount_minor)->toBe(125_050)
        ->and($bill->totalAmountMinor())->toBe(150_075);

    $firstService->update([
        'name' => 'Repriced consultation',
        'unit_price_minor' => 200_000,
    ]);

    expect($firstItem->fresh()->description)->toBe('Consultation assessment')
        ->and($firstItem->fresh()->amount_minor)->toBe(125_050);

    $firstItem->amount_minor = 1;
    expect(fn () => $firstItem->save())->toThrow(LogicException::class);
});

it('requires Bill items to match the Bill financial gate', function () {
    $consultationBill = Bill::factory()->create();
    $procedureService = ServiceCatalogItem::factory()->procedure()->create();

    expect(fn () => BillItem::factory()
        ->for($consultationBill)
        ->for($procedureService, 'serviceCatalogItem')
        ->create())->toThrow(LogicException::class);
});

it('enforces allowed types statuses and positive amounts at the database boundary', function () {
    $visit = Visit::factory()->create();

    expect(fn () => DB::table('service_catalog_items')->insert([
        'name' => 'Invalid category',
        'category' => 'other',
        'unit_price_minor' => 10_000,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('service_catalog_items')->insert([
        'name' => 'Invalid price',
        'category' => BillType::Consultation->value,
        'unit_price_minor' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('bills')->insert([
        'visit_id' => $visit->id,
        'bill_number' => 'BIL-BAD-TYPE',
        'type' => 'other',
        'status' => BillStatus::Open->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('bills')->insert([
        'visit_id' => $visit->id,
        'bill_number' => 'BIL-BAD-STATUS',
        'type' => BillType::Consultation->value,
        'status' => 'cancelled',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $bill = Bill::factory()->for($visit)->create();
    $service = ServiceCatalogItem::factory()->create();

    expect(fn () => DB::table('bill_items')->insert([
        'bill_id' => $bill->id,
        'service_catalog_item_id' => $service->id,
        'description' => $service->name,
        'amount_minor' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects orphan financial records and restricts removal of referenced records', function () {
    expect(fn () => DB::table('bills')->insert([
        'visit_id' => 999_999,
        'bill_number' => 'BIL-999999',
        'type' => BillType::Consultation->value,
        'status' => BillStatus::Open->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $bill = Bill::factory()->create();
    $service = ServiceCatalogItem::factory()->create();

    expect(fn () => DB::table('bill_items')->insert([
        'bill_id' => 999_999,
        'service_catalog_item_id' => $service->id,
        'description' => $service->name,
        'amount_minor' => $service->unit_price_minor,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $item = BillItem::factory()
        ->for($bill)
        ->for($service, 'serviceCatalogItem')
        ->create();

    expect(fn () => $bill->visit->delete())->toThrow(QueryException::class)
        ->and(fn () => $bill->delete())->toThrow(QueryException::class)
        ->and(fn () => $service->delete())->toThrow(QueryException::class)
        ->and($item->fresh())->not->toBeNull();
});

it('keeps financial clearance Bill-scoped while check-in state remains absent', function () {
    $visit = Visit::factory()->create();

    expect(Schema::hasTable('financial_clearances'))->toBeTrue()
        ->and(Route::has('billing.clearances.store'))->toBeTrue()
        ->and(Route::has('billing.index'))->toBeFalse()
        ->and(Route::has('visits.check-in'))->toBeFalse()
        ->and(method_exists($visit, 'financialClearance'))->toBeFalse();
});
