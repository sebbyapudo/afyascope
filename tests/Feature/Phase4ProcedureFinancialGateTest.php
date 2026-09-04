<?php

use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\RecordProcedurePayment;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\ConsultationStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\ProcedureBillingHandoff;
use App\Models\Receipt;
use App\Models\User;
use App\PaymentMethod;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

it('advances the authoritative procedure decision through the distinct second financial gate', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $visit = $handoff->visit;
    $consultation = $handoff->procedureDecision->consultation;
    $consultationBill = $visit->consultationBill;
    $consultationClearance = $consultationBill->financialClearance;

    expect($visit->workflowMessage())->toBe('Awaiting procedure billing');

    $this->actingAs($accountant)
        ->post(route('billing.procedures.store', $handoff))
        ->assertRedirect();

    $procedureBill = Bill::query()->where('type', BillType::Procedure)->sole();

    $this->actingAs($accountant)
        ->get(route('billing.bills.show', $procedureBill))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/show')
            ->where('bill.type', ['value' => 'procedure', 'label' => 'Procedure'])
            ->where('bill.visit.nextStep', 'Awaiting procedure payment')
            ->where('bill.payment', null)
            ->where('bill.financialClearance', null)
            ->missing('bill.clinicalRationale')
        );

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $procedureBill), [
            'payment_method' => PaymentMethod::Card->value,
        ])
        ->assertRedirect();

    $payment = Payment::query()->where('bill_id', $procedureBill->id)->sole();
    $receipt = $payment->receipt;

    expect($receipt)->toBeInstanceOf(Receipt::class)
        ->and($procedureBill->fresh()->status)->toBe(BillStatus::Paid)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting procedure financial clearance');

    $this->actingAs($accountant)
        ->get(route('billing.receipts.show', $receipt))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/receipts/show')
            ->where('receipt.bill.type', ['value' => 'procedure', 'label' => 'Procedure'])
            ->where('receipt.visit.nextStep', 'Awaiting procedure financial clearance')
        );

    $this->actingAs($accountant)
        ->get(route('billing.clearances.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bills.data', fn ($bills): bool => collect($bills)->contains(
                fn (array $item): bool => $item['id'] === $procedureBill->id
                    && $item['billType']['value'] === BillType::Procedure->value,
            ))
        );

    $this->actingAs($accountant)
        ->post(route('billing.clearances.store', $procedureBill))
        ->assertRedirect();

    $procedureClearance = FinancialClearance::query()
        ->where('bill_id', $procedureBill->id)
        ->sole();

    $this->actingAs($accountant)
        ->get(route('billing.clearances.show', $procedureClearance))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/clearances/show')
            ->where('clearance.bill.type', ['value' => 'procedure', 'label' => 'Procedure'])
            ->where('clearance.visit.nextStep', 'Ready for Nursing preparation')
            ->missing('clearance.clinicalRationale')
        );

    expect($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($consultation->fresh()->status)->toBe(ConsultationStatus::InProgress)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Nursing preparation')
        ->and($visit->consultationBill->is($consultationBill))->toBeTrue()
        ->and($visit->consultationBill->financialClearance->is($consultationClearance))->toBeTrue()
        ->and($visit->procedureBill->is($procedureBill))->toBeTrue()
        ->and($visit->consultationBill->id)->not->toBe($procedureBill->id)
        ->and(Schema::hasTable('nursing_preparations'))->toBeFalse();

    expect(AuditLog::query()->where('action', AuditAction::BillCreated)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::PaymentRecorded)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ReceiptIssued)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ProcedureFinancialCleared)->count())->toBe(1);
});

it('keeps procedure payment amount server controlled for under exact and over input', function (int $amountMinor) {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    $auditCount = AuditLog::query()->count();

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_minor' => $amountMinor,
        ])
        ->assertSessionHasErrors('amount_minor');

    expect(Payment::query()->where('bill_id', $bill->id)->count())->toBe(0)
        ->and(Receipt::query()->whereHas('payment', fn ($query) => $query->where('bill_id', $bill->id))->count())->toBe(0)
        ->and($bill->fresh()->status)->toBe(BillStatus::Open)
        ->and(AuditLog::query()->count())->toBe($auditCount);
})->with([
    'underpayment' => 1,
    'client-supplied exact amount' => 50_000,
    'overpayment' => 999_999,
]);

it('denies procedure financial writes to non-Accountant roles and redirects guests', function (StaffRole $role) {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);
    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);
    $actor = User::factory()->forRole($role)->create();
    $auditCount = AuditLog::query()->count();

    $this->actingAs($actor)
        ->post(route('billing.procedures.store', $handoff))
        ->assertForbidden();
    $this->actingAs($actor)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->assertForbidden();
    $this->actingAs($actor)
        ->post(route('billing.clearances.store', $bill))
        ->assertForbidden();

    auth()->logout();

    $this->post(route('billing.procedures.store', $handoff))->assertRedirect(route('login'));
    $this->post(route('billing.payments.store', $bill), [
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertRedirect(route('login'));
    $this->post(route('billing.clearances.store', $bill))->assertRedirect(route('login'));

    expect(FinancialClearance::query()->where('bill_id', $bill->id)->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCount);
})->with([
    StaffRole::Receptionist,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('denies an inactive Accountant and exposes no later clinical workflow routes', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $inactiveAccountant = User::factory()->forRole(StaffRole::Accountant)->inactive()->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);

    $this->actingAs($inactiveAccountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(Payment::query()->where('bill_id', $bill->id)->count())->toBe(0);

    app(RecordProcedurePayment::class)->handle($accountant, $bill, PaymentMethod::Cash);

    $this->actingAs($inactiveAccountant)
        ->post(route('billing.clearances.store', $bill))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(FinancialClearance::query()->where('bill_id', $bill->id)->count())->toBe(0);

    expect(Route::has('nursing.preparations.store'))->toBeFalse()
        ->and(Route::has('procedures.execute'))->toBeFalse()
        ->and(Route::has('clinical.consultations.finalize'))->toBeFalse();
});
