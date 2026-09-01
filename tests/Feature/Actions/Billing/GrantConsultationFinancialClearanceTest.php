<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\GrantConsultationFinancialClearance;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('grants one consultation clearance and records exactly one business audit', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = clearanceActionPaidBill(125_050);
    $visit = $bill->visit;

    expect($visit->workflowMessage())->toBe('Awaiting consultation financial clearance');

    $financialClearance = app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $bill,
    );

    expect($financialClearance->clearance_number)->toMatch('/^CLR-\d{6,}$/')
        ->and($financialClearance->bill->is($bill))->toBeTrue()
        ->and($financialClearance->grantedBy->is($accountant))->toBeTrue()
        ->and($bill->fresh()->status)->toBe(BillStatus::Paid)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Created)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting Reception check-in');

    $auditLog = AuditLog::query()->sole();

    expect($auditLog->action)->toBe(AuditAction::ConsultationFinancialCleared)
        ->and($auditLog->actor->is($accountant))->toBeTrue()
        ->and($auditLog->subject->is($financialClearance))->toBeTrue()
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveCount(6)
        ->and($auditLog->after_values)->toHaveKey('clearance_number', $financialClearance->clearance_number)
        ->and($auditLog->after_values)->toHaveKey('bill_id', $bill->id)
        ->and($auditLog->after_values)->toHaveKey('bill_number', $bill->bill_number)
        ->and($auditLog->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($auditLog->after_values)->toHaveKey('payment_id', $bill->payment->id)
        ->and($auditLog->after_values)->toHaveKey('receipt_id', $bill->payment->receipt->id);
});

it('rejects duplicate clearance without another record or audit', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = clearanceActionPaidBill();

    app(GrantConsultationFinancialClearance::class)->handle($accountant, $bill);

    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $bill,
    ))->toThrow(ValidationException::class);

    expect(FinancialClearance::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(1);
});

it('rejects open unpaid and missing-payment Bills without residue', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $openBill = clearanceActionBillWithItem();

    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $openBill,
    ))->toThrow(ValidationException::class);

    $openBill->status = BillStatus::Paid;
    $openBill->save();

    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $openBill,
    ))->toThrow(ValidationException::class);

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects paid Bills without a Receipt or exact successful Payment', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = clearanceActionBillWithItem(75_000);
    $payment = Payment::factory()->for($bill)->create();
    $bill->status = BillStatus::Paid;
    $bill->save();

    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $bill,
    ))->toThrow(ValidationException::class);

    Receipt::factory()->for($payment)->create();
    DB::table('payments')->where('id', $payment->id)->update(['amount_minor' => 1]);

    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $bill,
    ))->toThrow(ValidationException::class);

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects procedure Bills without clearance or audit residue', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = clearanceActionPaidBill();
    DB::table('bills')->where('id', $bill->id)->update([
        'type' => BillType::Procedure->value,
    ]);

    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $bill->fresh(),
    ))->toThrow(ValidationException::class);

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('enforces Accountant authorization at the action boundary', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $bill = clearanceActionPaidBill();

    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $receptionist,
        $bill,
    ))->toThrow(AuthorizationException::class);

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls back clearance when its audit write fails', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = clearanceActionPaidBill();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Clearance audit failed.'));

    expect(fn () => (new GrantConsultationFinancialClearance($recordAuditLog))->handle(
        $accountant,
        $bill,
    ))->toThrow(RuntimeException::class, 'Clearance audit failed.');

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($bill->fresh()->status)->toBe(BillStatus::Paid);
});

function clearanceActionPaidBill(int $amountMinor = 50_000): Bill
{
    $bill = clearanceActionBillWithItem($amountMinor);
    $payment = Payment::factory()->for($bill)->create();

    Receipt::factory()->for($payment)->create();
    $bill->status = BillStatus::Paid;
    $bill->save();

    return $bill;
}

function clearanceActionBillWithItem(int $amountMinor = 50_000): Bill
{
    $bill = Bill::factory()->create();
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => $amountMinor,
    ]);

    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    return $bill;
}
