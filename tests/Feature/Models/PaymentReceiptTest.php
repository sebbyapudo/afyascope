<?php

use App\BillStatus;
use App\BillType;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('defines the Bill Payment Receipt and recording-user relationships without duplicating Patient data', function () {
    $bill = paymentReceiptBill(125_050);
    $accountant = User::factory()->create();
    $payment = Payment::factory()->for($bill)->for($accountant, 'recordedBy')->create();
    $receipt = Receipt::factory()->for($payment)->create();

    expect($payment->bill->is($bill))->toBeTrue()
        ->and($bill->payment->is($payment))->toBeTrue()
        ->and($payment->recordedBy->is($accountant))->toBeTrue()
        ->and($payment->receipt->is($receipt))->toBeTrue()
        ->and($receipt->payment->is($payment))->toBeTrue()
        ->and(Schema::hasColumn('payments', 'patient_id'))->toBeFalse()
        ->and(Schema::hasColumn('receipts', 'patient_id'))->toBeFalse()
        ->and(Schema::hasColumn('receipts', 'bill_id'))->toBeFalse();
});

it('derives the exact Bill total and generates immutable sequential references', function () {
    $bill = paymentReceiptBill(125_050);
    $payment = Payment::factory()->for($bill)->create([
        'id' => 73,
        'payment_number' => 'PAY-SUPPLIED',
        'amount_minor' => 1,
        'recorded_at' => '2020-01-01 00:00:00',
    ]);
    $receipt = Receipt::factory()->for($payment)->create([
        'id' => 81,
        'receipt_number' => 'RCT-SUPPLIED',
        'issued_at' => '2020-01-01 00:00:00',
    ]);

    expect($payment->payment_number)->toBe('PAY-000073')
        ->and($payment->amount_minor)->toBe(125_050)
        ->and($payment->recorded_at->year)->toBeGreaterThan(2020)
        ->and($receipt->receipt_number)->toBe('RCT-000081')
        ->and($receipt->issued_at->year)->toBeGreaterThan(2020);

    $payment->amount_minor = 1;
    expect(fn () => $payment->save())->toThrow(LogicException::class);

    $receipt->receipt_number = 'RCT-CHANGED';
    expect(fn () => $receipt->save())->toThrow(LogicException::class);
});

it('produces unique Payment and Receipt references during burst-style creation', function () {
    $payments = collect();
    $receipts = collect();

    foreach (range(1, 20) as $index) {
        $payment = Payment::factory()->for(paymentReceiptBill(10_000 + $index))->create();
        $payments->push($payment);
        $receipts->push(Receipt::factory()->for($payment)->create());
    }

    expect($payments->pluck('payment_number')->unique())->toHaveCount(20)
        ->and($receipts->pluck('receipt_number')->unique())->toHaveCount(20);

    $payments->each(function (Payment $payment): void {
        expect($payment->payment_number)->toBe(
            'PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
        );
    });

    $receipts->each(function (Receipt $receipt): void {
        expect($receipt->receipt_number)->toBe(
            'RCT-'.str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT),
        );
    });
});

it('uses database constraints as duplicate and concurrency backstops', function () {
    $bill = paymentReceiptBill(50_000);
    $user = User::factory()->create();
    $payment = Payment::factory()->for($bill)->for($user, 'recordedBy')->create();
    Receipt::factory()->for($payment)->create();

    expect(fn () => DB::table('payments')->insert([
        'bill_id' => $bill->id,
        'payment_number' => 'PAY-DUPLICATE-BILL',
        'amount_minor' => 50_000,
        'method' => PaymentMethod::Cash->value,
        'recorded_by_user_id' => $user->id,
        'recorded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('receipts')->insert([
        'payment_id' => $payment->id,
        'receipt_number' => 'RCT-DUPLICATE-PAYMENT',
        'issued_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('enforces allowed methods positive money and paid Bill state at the database boundary', function () {
    $bill = paymentReceiptBill(50_000);
    $user = User::factory()->create();

    expect(fn () => DB::table('payments')->insert([
        'bill_id' => $bill->id,
        'payment_number' => 'PAY-BAD-METHOD',
        'amount_minor' => 50_000,
        'method' => 'cheque',
        'recorded_by_user_id' => $user->id,
        'recorded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('payments')->insert([
        'bill_id' => $bill->id,
        'payment_number' => 'PAY-ZERO-AMOUNT',
        'amount_minor' => 0,
        'method' => PaymentMethod::Cash->value,
        'recorded_by_user_id' => $user->id,
        'recorded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $bill->status = BillStatus::Paid;
    $bill->save();

    expect($bill->fresh()->status)->toBe(BillStatus::Paid);

    expect(fn () => DB::table('bills')->where('id', $bill->id)->update([
        'status' => 'refunded',
    ]))->toThrow(QueryException::class);
});

it('rejects orphan records and restricts removal of financial history', function () {
    $bill = paymentReceiptBill(50_000);
    $user = User::factory()->create();
    $payment = Payment::factory()->for($bill)->for($user, 'recordedBy')->create();
    $receipt = Receipt::factory()->for($payment)->create();

    expect(fn () => DB::table('payments')->insert([
        'bill_id' => 999_999,
        'payment_number' => 'PAY-ORPHAN',
        'amount_minor' => 50_000,
        'method' => PaymentMethod::Cash->value,
        'recorded_by_user_id' => $user->id,
        'recorded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => $bill->delete())->toThrow(QueryException::class)
        ->and(fn () => $user->delete())->toThrow(QueryException::class)
        ->and(fn () => $payment->delete())->toThrow(QueryException::class)
        ->and($receipt->fresh())->not->toBeNull();
});

it('exposes payment Receipt and financial-clearance records without check-in behavior', function () {
    expect(Schema::hasTable('payments'))->toBeTrue()
        ->and(Schema::hasTable('receipts'))->toBeTrue()
        ->and(Schema::hasTable('financial_clearances'))->toBeTrue()
        ->and(Route::has('billing.payments.store'))->toBeTrue()
        ->and(Route::has('billing.receipts.show'))->toBeTrue()
        ->and(Route::has('billing.clearances.store'))->toBeTrue()
        ->and(Route::has('visits.check-in'))->toBeFalse()
        ->and(Route::has('billing.procedure-payments.store'))->toBeFalse();
});

function paymentReceiptBill(int $amountMinor): Bill
{
    $bill = Bill::factory()->create(['type' => BillType::Consultation]);
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => $amountMinor,
    ]);
    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    return $bill;
}
