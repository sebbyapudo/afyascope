<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\RecordProcedurePayment;
use App\AuditAction;
use App\BillStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\Receipt;
use App\Models\User;
use App\PaymentMethod;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('records the exact procedure payment and issues one Receipt atomically', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = procedurePaymentBill($accountant, 275_050);
    $visit = $bill->visit;

    $receipt = app(RecordProcedurePayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::MobileMoney,
    );

    $payment = $receipt->payment;

    expect($payment->payment_number)->toMatch('/^PAY-\d{6,}$/')
        ->and($payment->bill->is($bill))->toBeTrue()
        ->and($payment->amount_minor)->toBe(275_050)
        ->and($payment->method)->toBe(PaymentMethod::MobileMoney)
        ->and($payment->recordedBy->is($accountant))->toBeTrue()
        ->and($receipt->receipt_number)->toMatch('/^RCT-\d{6,}$/')
        ->and($bill->fresh()->status)->toBe(BillStatus::Paid)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting procedure financial clearance');

    expect(AuditLog::query()->where('action', AuditAction::PaymentRecorded)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ReceiptIssued)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureFinancialCleared)->count())->toBe(0);
});

it('rejects duplicate procedure payment without another Receipt or audit', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = procedurePaymentBill($accountant);

    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(RecordProcedurePayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::Card,
    ))->toThrow(ValidationException::class);

    expect(Payment::query()->where('bill_id', $bill->id)->count())->toBe(1)
        ->and(Receipt::query()->whereHas('payment', fn ($query) => $query->where('bill_id', $bill->id))->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('rejects a consultation Bill at the procedure payment boundary', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $consultationBill = Bill::factory()->create();

    expect(fn () => app(RecordProcedurePayment::class)->handle(
        $accountant,
        $consultationBill,
        PaymentMethod::Cash,
    ))->toThrow(ValidationException::class);

    expect(Payment::query()->where('bill_id', $consultationBill->id)->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0);
});

it('enforces active Accountant authorization at the procedure payment action boundary', function (StaffRole $role) {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $actor = User::factory()->forRole($role)->create();
    $bill = procedurePaymentBill($accountant);

    expect(fn () => app(RecordProcedurePayment::class)->handle(
        $actor,
        $bill,
        PaymentMethod::Cash,
    ))->toThrow(AuthorizationException::class);

    expect(Payment::query()->where('bill_id', $bill->id)->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('rejects an inactive Accountant without financial or audit residue', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $inactiveAccountant = User::factory()->forRole(StaffRole::Accountant)->inactive()->create();
    $bill = procedurePaymentBill($accountant);
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(RecordProcedurePayment::class)->handle(
        $inactiveAccountant,
        $bill,
        PaymentMethod::Cash,
    ))->toThrow(AuthorizationException::class);

    expect(Payment::query()->where('bill_id', $bill->id)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('rolls back Payment Receipt Bill state and audits when receipt auditing fails', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = procedurePaymentBill($accountant);
    $auditCount = AuditLog::query()->count();
    $receiptCount = Receipt::query()->count();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')->once()->ordered()->andReturn(new AuditLog);
    $recordAuditLog->shouldReceive('handle')->once()->ordered()->andThrow(
        new RuntimeException('Procedure receipt audit failed.'),
    );

    expect(fn () => (new RecordProcedurePayment($recordAuditLog))->handle(
        $accountant,
        $bill,
        PaymentMethod::Cash,
    ))->toThrow(RuntimeException::class, 'Procedure receipt audit failed.');

    expect(Payment::query()->where('bill_id', $bill->id)->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe($receiptCount)
        ->and($bill->fresh()->status)->toBe(BillStatus::Open)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

function procedurePaymentBill(User $accountant, int $amountMinor = 125_000): Bill
{
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $handoff->serviceCatalogItem->update(['unit_price_minor' => $amountMinor]);

    return app(CreateProcedureBill::class)->handle($accountant, $handoff);
}
