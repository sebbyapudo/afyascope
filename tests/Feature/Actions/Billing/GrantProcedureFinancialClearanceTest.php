<?php

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantProcedureFinancialClearance;
use App\Actions\Billing\RecordProcedurePayment;
use App\AuditAction;
use App\BillStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\PaymentMethod;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('grants one procedure clearance and records exactly one business audit', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = procedureClearancePaidBill($accountant, 325_050);
    $visit = $bill->visit;
    $consultation = $visit->consultation;

    expect($visit->workflowMessage())->toBe('Awaiting procedure financial clearance');

    $clearance = app(GrantProcedureFinancialClearance::class)->handle($accountant, $bill);

    expect($clearance->clearance_number)->toMatch('/^CLR-\d{6,}$/')
        ->and($clearance->bill->is($bill))->toBeTrue()
        ->and($clearance->grantedBy->is($accountant))->toBeTrue()
        ->and($bill->fresh()->status)->toBe(BillStatus::Paid)
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Nursing preparation')
        ->and($consultation->fresh()->status->value)->toBe('in_progress');

    $auditLog = AuditLog::query()
        ->where('action', AuditAction::ProcedureFinancialCleared)
        ->sole();

    expect($auditLog->actor->is($accountant))->toBeTrue()
        ->and($auditLog->subject->is($clearance))->toBeTrue()
        ->and($auditLog->before_values)->toBeNull()
        ->and($auditLog->after_values)->toHaveCount(7)
        ->and($auditLog->after_values)->toHaveKey('bill_id', $bill->id)
        ->and($auditLog->after_values)->toHaveKey('visit_id', $visit->id)
        ->and($auditLog->after_values)->toHaveKey(
            'procedure_billing_handoff_id',
            $bill->procedure_billing_handoff_id,
        );
});

it('rejects procedure clearance before exact Payment and Receipt exist', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(GrantProcedureFinancialClearance::class)->handle(
        $accountant,
        $bill,
    ))->toThrow(ValidationException::class);

    $payment = Payment::factory()->for($bill)->for($accountant, 'recordedBy')->create();
    $bill->status = BillStatus::Paid;
    $bill->save();

    expect(fn () => app(GrantProcedureFinancialClearance::class)->handle(
        $accountant,
        $bill,
    ))->toThrow(ValidationException::class);

    expect(FinancialClearance::query()->where('bill_id', $bill->id)->count())->toBe(0)
        ->and(Receipt::query()->where('payment_id', $payment->id)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('rejects duplicate procedure clearance without another record or audit', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = procedureClearancePaidBill($accountant);

    app(GrantProcedureFinancialClearance::class)->handle($accountant, $bill);
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(GrantProcedureFinancialClearance::class)->handle(
        $accountant,
        $bill,
    ))->toThrow(ValidationException::class);

    expect(FinancialClearance::query()->where('bill_id', $bill->id)->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('rejects a consultation Bill and a malformed procedure handoff without clearance audit', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $procedureBill = procedureClearancePaidBill($accountant);
    $consultationBill = FinancialClearance::factory()->create()->bill;
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(GrantProcedureFinancialClearance::class)->handle(
        $accountant,
        $consultationBill,
    ))->toThrow(ValidationException::class);

    $otherProcedure = ServiceCatalogItem::factory()->procedure()->create();
    DB::table('procedure_billing_handoffs')
        ->where('id', $procedureBill->procedure_billing_handoff_id)
        ->update(['service_catalog_item_id' => $otherProcedure->id]);

    expect(fn () => app(GrantProcedureFinancialClearance::class)->handle(
        $accountant,
        $procedureBill,
    ))->toThrow(ValidationException::class);

    expect(FinancialClearance::query()->where('bill_id', $procedureBill->id)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('enforces active Accountant authorization at the procedure clearance action boundary', function (StaffRole $role) {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $actor = User::factory()->forRole($role)->create();
    $bill = procedureClearancePaidBill($accountant);

    expect(fn () => app(GrantProcedureFinancialClearance::class)->handle(
        $actor,
        $bill,
    ))->toThrow(AuthorizationException::class);

    expect(FinancialClearance::query()->where('bill_id', $bill->id)->count())->toBe(0);
})->with([
    StaffRole::Receptionist,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('rolls back procedure clearance when its audit write fails', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = procedureClearancePaidBill($accountant);
    $auditCount = AuditLog::query()->count();
    $recordAuditLog = Mockery::mock(RecordAuditLog::class);
    $recordAuditLog->shouldReceive('handle')->once()->andThrow(
        new RuntimeException('Procedure clearance audit failed.'),
    );

    expect(fn () => (new GrantProcedureFinancialClearance($recordAuditLog))->handle(
        $accountant,
        $bill,
    ))->toThrow(RuntimeException::class, 'Procedure clearance audit failed.');

    expect(FinancialClearance::query()->where('bill_id', $bill->id)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

function procedureClearancePaidBill(User $accountant, int $amountMinor = 125_000): Bill
{
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $handoff->serviceCatalogItem->update(['unit_price_minor' => $amountMinor]);
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);

    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);

    return $bill->fresh(['items', 'payment.receipt', 'procedureBillingHandoff', 'visit.consultation']);
}
