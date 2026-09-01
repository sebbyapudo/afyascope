<?php

use App\Actions\Billing\RecordConsultationPayment;
use App\AuditAction;
use App\BillStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\PaymentMethod;
use App\StaffRole;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only open unpaid consultation Bills in deterministic queue order', function () {
    $accountant = paymentControllerAccountant();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
    ]);
    $oldest = paymentControllerBill(80_000, $patient, [
        'created_at' => '2026-09-01 08:00:00',
    ]);
    $newest = paymentControllerBill(90_000, $patient, [
        'created_at' => '2026-09-01 10:00:00',
    ]);
    $paid = paymentControllerBill(100_000, $patient, [
        'created_at' => '2026-09-01 07:00:00',
    ]);
    app(RecordConsultationPayment::class)->handle($accountant, $paid, PaymentMethod::Cash);
    $procedure = Bill::factory()->procedure()->create([
        'created_at' => '2026-09-01 09:00:00',
    ]);
    $procedureService = ServiceCatalogItem::factory()->procedure()->create();
    BillItem::factory()->for($procedure)->for($procedureService, 'serviceCatalogItem')->create();

    $this->actingAs($accountant)
        ->get(route('billing.payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/payments/index')
            ->where('bills.data', fn ($bills): bool => collect($bills)->pluck('id')->all() === [
                $oldest->id,
                $newest->id,
            ])
            ->where('bills.pagination.total', 2)
            ->where('bills.data.0.patient.patientNumber', $patient->patient_number)
            ->where('bills.data.0.totalAmountMinor', 80_000)
            ->missing('bills.data.0.patient_id')
            ->missing('bills.data.0.payment')
        );
});

it('shows a sanitized exact-amount payment confirmation', function () {
    $accountant = paymentControllerAccountant();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
    ]);
    $bill = paymentControllerBill(125_050, $patient);

    $this->actingAs($accountant)
        ->get(route('billing.payments.create', $bill))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/payments/create')
            ->where('bill.id', $bill->id)
            ->where('bill.totalAmountMinor', 125_050)
            ->where('bill.patient', [
                'patientNumber' => $patient->patient_number,
                'name' => 'Amina Kamau',
            ])
            ->where('paymentMethods', [
                ['value' => 'cash', 'label' => 'Cash'],
                ['value' => 'mobile_money', 'label' => 'Mobile money'],
                ['value' => 'card', 'label' => 'Card'],
            ])
            ->missing('bill.visit.patient_id')
            ->missing('bill.patient.email')
            ->missing('bill.auditLogs')
            ->missing('bill.financialClearance')
        );
});

it('records payment and redirects to the issued Receipt', function () {
    $accountant = paymentControllerAccountant();
    $bill = paymentControllerBill(75_025);

    $response = $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Card->value,
        ]);

    $payment = Payment::query()->sole();
    $receipt = Receipt::query()->sole();

    $response
        ->assertRedirect(route('billing.receipts.show', $receipt))
        ->assertSessionHas('status', "Payment recorded and Receipt {$receipt->receipt_number} issued.");

    expect($payment->amount_minor)->toBe(75_025)
        ->and($payment->method)->toBe(PaymentMethod::Card)
        ->and($bill->fresh()->status)->toBe(BillStatus::Paid)
        ->and(AuditLog::query()->where('action', AuditAction::PaymentRecorded)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ReceiptIssued)->count())->toBe(1);
});

it('rejects client-controlled financial and receipt fields', function () {
    $accountant = paymentControllerAccountant();
    $bill = paymentControllerBill(75_025);

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
            'bill_id' => 999_999,
            'payment_number' => 'PAY-SUPPLIED',
            'amount' => '0.01',
            'amount_minor' => 1,
            'recorded_at' => '2020-01-01 00:00:00',
            'recorded_by_user_id' => 999_999,
            'receipt_number' => 'RCT-SUPPLIED',
            'issued_at' => '2020-01-01 00:00:00',
        ])
        ->assertSessionHasErrors([
            'bill_id',
            'payment_number',
            'amount',
            'amount_minor',
            'recorded_at',
            'recorded_by_user_id',
            'receipt_number',
            'issued_at',
        ]);

    expect(Payment::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and($bill->fresh()->status)->toBe(BillStatus::Open);
});

it('validates the explicit locally recorded payment methods', function (?string $method, string $message) {
    $accountant = paymentControllerAccountant();
    $bill = paymentControllerBill(75_025);

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), array_filter([
            'payment_method' => $method,
        ], fn ($value): bool => $value !== null))
        ->assertSessionHasErrors(['payment_method' => $message]);

    expect(Payment::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'missing method' => [null, 'Select a payment method.'],
    'unsupported cheque' => ['cheque', 'Select a valid payment method.'],
]);

it('rejects duplicate payment attempts without residue', function () {
    $accountant = paymentControllerAccountant();
    $bill = paymentControllerBill(75_025);

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->assertRedirect();

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->assertSessionHasErrors([
            'bill' => 'Only an open consultation Bill can receive payment.',
        ]);

    expect(Payment::query()->count())->toBe(1)
        ->and(Receipt::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe(2);
});

it('rejects procedure Bill payment through direct URLs', function () {
    $accountant = paymentControllerAccountant();
    $bill = Bill::factory()->procedure()->create();
    $service = ServiceCatalogItem::factory()->procedure()->create();
    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    $this->actingAs($accountant)
        ->get(route('billing.payments.create', $bill))
        ->assertNotFound();

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->assertSessionHasErrors([
            'bill' => 'Only an open consultation Bill can receive payment.',
        ]);

    expect(Payment::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('redirects confirmation for an already paid Bill to its Receipt', function () {
    $accountant = paymentControllerAccountant();
    $bill = paymentControllerBill(75_025);
    $receipt = app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::Cash,
    );

    $this->actingAs($accountant)
        ->get(route('billing.payments.create', $bill))
        ->assertRedirect(route('billing.receipts.show', $receipt));
});

it('renders the issued Receipt with payment context and no later-stage data', function () {
    $accountant = paymentControllerAccountant();
    $patient = Patient::factory()->create();
    $bill = paymentControllerBill(125_050, $patient);
    $receipt = app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::MobileMoney,
    );

    $this->actingAs($accountant)
        ->get(route('billing.receipts.show', $receipt))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/receipts/show')
            ->where('receipt.receiptNumber', $receipt->receipt_number)
            ->where('receipt.payment.paymentNumber', $receipt->payment->payment_number)
            ->where('receipt.payment.amountMinor', 125_050)
            ->where('receipt.payment.method', [
                'value' => 'mobile_money',
                'label' => 'Mobile money',
            ])
            ->where('receipt.payment.recordedBy', $accountant->name)
            ->where('receipt.bill.status', ['value' => 'paid', 'label' => 'Paid'])
            ->where('receipt.bill.financialClearance', null)
            ->where('receipt.visit.status', ['value' => 'created', 'label' => 'Created'])
            ->where('receipt.visit.nextStep', 'Awaiting consultation financial clearance')
            ->where('receipt.patient.patientNumber', $patient->patient_number)
            ->missing('receipt.payment.bill_id')
            ->missing('receipt.patient.email')
            ->missing('receipt.auditLogs')
            ->missing('receipt.financialClearance')
            ->missing('receipt.visit.checkedInAt')
        );
});

it('projects paid Bill and awaiting-clearance messaging across existing detail screens', function () {
    $accountant = paymentControllerAccountant();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $visit = Visit::factory()->for($patient)->create([
        'appointment_id' => $appointment->id,
    ]);
    $bill = Bill::factory()->for($visit)->create();
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => 125_050,
    ]);
    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();
    $receipt = app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::Cash,
    );

    $this->actingAs($accountant)
        ->get(route('billing.bills.show', $bill))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bill.status', ['value' => 'paid', 'label' => 'Paid'])
            ->where('bill.payment.paymentNumber', $receipt->payment->payment_number)
            ->where('bill.payment.amountMinor', 125_050)
            ->where('bill.payment.receipt', [
                'id' => $receipt->id,
                'receiptNumber' => $receipt->receipt_number,
            ])
            ->where('bill.visit.nextStep', 'Awaiting consultation financial clearance')
            ->where('bill.financialClearance', null)
            ->missing('bill.checkIn')
        );

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.status', ['value' => 'created', 'label' => 'Created'])
            ->where('visit.nextStep', 'Awaiting consultation financial clearance')
            ->missing('visit.payment')
            ->missing('visit.clearance')
        );

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.nextStep', 'Awaiting consultation financial clearance')
            ->missing('visitHistory.data.0.payment')
        );

    $this->actingAs($receptionist)
        ->get(route('appointments.show', $appointment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointment.linkedVisit.nextStep', 'Awaiting consultation financial clearance')
            ->missing('appointment.linkedVisit.payment')
        );
});

it('redirects guests from every payment and Receipt endpoint', function () {
    $bill = paymentControllerBill(75_025);
    $payment = Payment::factory()->for($bill)->create();
    $receipt = Receipt::factory()->for($payment)->create();

    $this->get(route('billing.payments.index'))->assertRedirect(route('login'));
    $this->get(route('billing.payments.create', $bill))->assertRedirect(route('login'));
    $this->post(route('billing.payments.store', $bill), [
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertRedirect(route('login'));
    $this->get(route('billing.receipts.show', $receipt))->assertRedirect(route('login'));
});

it('denies every non-Accountant role from direct payment and Receipt URLs', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $bill = paymentControllerBill(75_025);
    $payment = Payment::factory()->for($bill)->create();
    $receipt = Receipt::factory()->for($payment)->create();

    $this->actingAs($actor)->get(route('billing.payments.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('billing.payments.create', $bill))->assertForbidden();
    $this->actingAs($actor)->post(route('billing.payments.store', $bill), [
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertForbidden();
    $this->actingAs($actor)->get(route('billing.receipts.show', $receipt))->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('keeps check-in procedure payment and payment deletion routes absent after clearance routing is introduced', function () {
    expect(Route::has('billing.clearances.store'))->toBeTrue()
        ->and(Route::has('visits.check-in'))->toBeFalse()
        ->and(Route::has('billing.procedure-payments.store'))->toBeFalse()
        ->and(Route::has('billing.payments.destroy'))->toBeFalse()
        ->and(Route::has('billing.receipts.destroy'))->toBeFalse();
});

function paymentControllerAccountant(): User
{
    return User::factory()->forRole(StaffRole::Accountant)->create();
}

/**
 * @param  array<string, mixed>  $billAttributes
 */
function paymentControllerBill(
    int $amountMinor,
    ?Patient $patient = null,
    array $billAttributes = [],
): Bill {
    $patient ??= Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();
    $bill = Bill::factory()->for($visit)->create($billAttributes);
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => $amountMinor,
    ]);
    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    return $bill;
}
