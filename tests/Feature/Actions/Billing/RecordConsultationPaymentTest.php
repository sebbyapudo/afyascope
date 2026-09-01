<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\RecordConsultationPayment;
use App\AuditAction;
use App\BillStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\PaymentMethod;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('records exact consultation payment and issues its Receipt atomically', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $bill = paymentActionBill($visit, 125_050);

    $receipt = app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::MobileMoney,
    );

    $payment = $receipt->payment;

    expect($payment->payment_number)->toMatch('/^PAY-\d{6,}$/')
        ->and($payment->bill->is($bill))->toBeTrue()
        ->and($payment->amount_minor)->toBe(125_050)
        ->and($payment->method)->toBe(PaymentMethod::MobileMoney)
        ->and($payment->recordedBy->is($accountant))->toBeTrue()
        ->and($receipt->receipt_number)->toMatch('/^RCT-\d{6,}$/')
        ->and($bill->fresh()->status)->toBe(BillStatus::Paid)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Created)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting consultation financial clearance');

    $auditLogs = AuditLog::query()->orderBy('id')->get();

    expect($auditLogs)->toHaveCount(2)
        ->and($auditLogs[0]->action)->toBe(AuditAction::PaymentRecorded)
        ->and($auditLogs[0]->actor->is($accountant))->toBeTrue()
        ->and($auditLogs[0]->subject->is($payment))->toBeTrue()
        ->and($auditLogs[0]->before_values)->toBeNull()
        ->and($auditLogs[0]->after_values)->toHaveCount(5)
        ->and($auditLogs[0]->after_values)->toHaveKey('payment_number', $payment->payment_number)
        ->and($auditLogs[0]->after_values)->toHaveKey('bill_id', $bill->id)
        ->and($auditLogs[0]->after_values)->toHaveKey('bill_number', $bill->bill_number)
        ->and($auditLogs[0]->after_values)->toHaveKey('amount_minor', 125_050)
        ->and($auditLogs[0]->after_values)->toHaveKey('method', PaymentMethod::MobileMoney->value)
        ->and($auditLogs[1]->action)->toBe(AuditAction::ReceiptIssued)
        ->and($auditLogs[1]->actor->is($accountant))->toBeTrue()
        ->and($auditLogs[1]->subject->is($receipt))->toBeTrue()
        ->and($auditLogs[1]->before_values)->toBeNull()
        ->and($auditLogs[1]->after_values)->toHaveCount(3)
        ->and($auditLogs[1]->after_values)->toHaveKey('receipt_number', $receipt->receipt_number)
        ->and($auditLogs[1]->after_values)->toHaveKey('payment_id', $payment->id)
        ->and($auditLogs[1]->after_values)->toHaveKey('bill_id', $bill->id);
});

it('rejects duplicate payment without another Receipt or audit event', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = paymentActionBill(Visit::factory()->create(), 80_000);

    app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::Cash,
    );

    expect(fn () => app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::Card,
    ))->toThrow(ValidationException::class);

    expect(Payment::query()->count())->toBe(1)
        ->and(Receipt::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(2);
});

it('rejects a procedure Bill without financial or audit residue', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $bill = Bill::factory()->for($visit)->procedure()->create();
    $service = ServiceCatalogItem::factory()->procedure()->create([
        'unit_price_minor' => 200_000,
    ]);
    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    expect(fn () => app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::Cash,
    ))->toThrow(ValidationException::class);

    expect(Payment::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($bill->fresh()->status)->toBe(BillStatus::Open);
});

it('enforces Accountant authorization at the payment action boundary', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $bill = paymentActionBill(Visit::factory()->create(), 80_000);

    expect(fn () => app(RecordConsultationPayment::class)->handle(
        $receptionist,
        $bill,
        PaymentMethod::Cash,
    ))->toThrow(AuthorizationException::class);

    expect(Payment::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back payment Receipt Bill state and audits when receipt audit fails', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = paymentActionBill(Visit::factory()->create(), 80_000);
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->ordered()
        ->andReturn(new AuditLog);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->ordered()
        ->andThrow(new RuntimeException('Receipt audit failed.'));

    expect(fn () => (new RecordConsultationPayment($recordAuditLog))->handle(
        $accountant,
        $bill,
        PaymentMethod::Cash,
    ))->toThrow(RuntimeException::class, 'Receipt audit failed.');

    expect(Payment::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($bill->fresh()->status)->toBe(BillStatus::Open);
});

function paymentActionBill(Visit $visit, int $amountMinor): Bill
{
    $bill = Bill::factory()->for($visit)->create();
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => $amountMinor,
    ]);
    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    return $bill;
}
