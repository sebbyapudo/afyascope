<?php

use App\BillStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('relates one immutable clearance to its Bill and granting user without duplicating Visit or Patient data', function () {
    $bill = financialClearanceEligibleBill();
    $accountant = User::factory()->create();
    $financialClearance = FinancialClearance::factory()
        ->for($bill)
        ->for($accountant, 'grantedBy')
        ->create();

    expect($financialClearance->bill->is($bill))->toBeTrue()
        ->and($bill->financialClearance->is($financialClearance))->toBeTrue()
        ->and($financialClearance->grantedBy->is($accountant))->toBeTrue()
        ->and(Schema::hasColumn('financial_clearances', 'visit_id'))->toBeFalse()
        ->and(Schema::hasColumn('financial_clearances', 'patient_id'))->toBeFalse()
        ->and(Schema::hasColumn('financial_clearances', 'payment_id'))->toBeFalse();
});

it('generates a server-controlled sequential reference and immutable business fields', function () {
    $bill = financialClearanceEligibleBill();
    $accountant = User::factory()->create();
    $financialClearance = FinancialClearance::factory()
        ->for($bill)
        ->for($accountant, 'grantedBy')
        ->create([
            'id' => 47,
            'clearance_number' => 'CLR-SUPPLIED',
            'granted_at' => '2020-01-01 00:00:00',
        ]);

    expect($financialClearance->clearance_number)->toBe('CLR-000047')
        ->and($financialClearance->granted_at->year)->toBeGreaterThan(2020);

    $financialClearance->clearance_number = 'CLR-CHANGED';

    expect(fn () => $financialClearance->save())->toThrow(LogicException::class);
});

it('produces unique references during burst-style creation', function () {
    $clearances = collect();

    foreach (range(1, 20) as $index) {
        $clearances->push(FinancialClearance::factory()
            ->for(financialClearanceEligibleBill(10_000 + $index))
            ->create());
    }

    expect($clearances->pluck('clearance_number')->unique())->toHaveCount(20);

    $clearances->each(function (FinancialClearance $financialClearance): void {
        expect($financialClearance->clearance_number)->toBe(
            'CLR-'.str_pad((string) $financialClearance->id, 6, '0', STR_PAD_LEFT),
        );
    });
});

it('uses database uniqueness and foreign keys as concurrency and history backstops', function () {
    $bill = financialClearanceEligibleBill();
    $accountant = User::factory()->create();
    FinancialClearance::factory()
        ->for($bill)
        ->for($accountant, 'grantedBy')
        ->create();

    expect(fn () => DB::table('financial_clearances')->insert([
        'bill_id' => $bill->id,
        'clearance_number' => 'CLR-DUPLICATE-BILL',
        'granted_by_user_id' => $accountant->id,
        'granted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => $bill->delete())->toThrow(QueryException::class)
        ->and(fn () => $accountant->delete())->toThrow(QueryException::class);
});

it('rejects malformed direct model creation without financial residue', function () {
    $openBill = Bill::factory()->has(BillItem::factory(), 'items')->create();
    $accountant = User::factory()->create();

    expect(fn () => FinancialClearance::factory()
        ->for($openBill)
        ->for($accountant, 'grantedBy')
        ->create())->toThrow(LogicException::class);

    expect(FinancialClearance::query()->count())->toBe(0);
});

function financialClearanceEligibleBill(int $amountMinor = 50_000): Bill
{
    $bill = Bill::factory()->create();
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => $amountMinor,
    ]);
    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();
    $payment = Payment::factory()->for($bill)->create();

    Receipt::factory()->for($payment)->create();
    $bill->status = BillStatus::Paid;
    $bill->save();

    return $bill;
}
