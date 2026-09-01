<?php

use App\Actions\Billing\GrantConsultationFinancialClearance;
use App\Actions\Billing\RecordConsultationPayment;
use App\AuditAction;
use App\BillStatus;
use App\BillType;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FinancialClearance;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\PaymentMethod;
use App\StaffRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('queues only fully paid uncleared consultation Bills in deterministic order', function () {
    $accountant = clearanceControllerAccountant();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
    ]);
    $oldest = clearanceControllerPaidBill(80_000, $patient, [
        'created_at' => '2026-09-01 08:00:00',
    ]);
    $newest = clearanceControllerPaidBill(90_000, $patient, [
        'created_at' => '2026-09-01 10:00:00',
    ]);
    $alreadyCleared = clearanceControllerPaidBill(100_000, $patient, [
        'created_at' => '2026-09-01 07:00:00',
    ]);
    app(GrantConsultationFinancialClearance::class)->handle($accountant, $alreadyCleared);
    $open = clearanceControllerBillWithItem(70_000, $patient, [
        'created_at' => '2026-09-01 06:00:00',
    ]);
    $procedure = clearanceControllerPaidBill(110_000, $patient, [
        'created_at' => '2026-09-01 09:00:00',
    ]);
    DB::table('bills')->where('id', $procedure->id)->update([
        'type' => BillType::Procedure->value,
    ]);

    $this->actingAs($accountant)
        ->get(route('billing.clearances.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/clearances/index')
            ->where('bills.data', fn ($bills): bool => collect($bills)->pluck('id')->all() === [
                $oldest->id,
                $newest->id,
            ])
            ->where('bills.pagination.total', 2)
            ->where('bills.data.0.patient', [
                'patientNumber' => $patient->patient_number,
                'name' => 'Amina Kamau',
            ])
            ->where('bills.data.0.payment.amountMinor', 80_000)
            ->where('bills.data.0.visit.nextStep', 'Awaiting consultation financial clearance')
            ->missing('bills.data.0.patient.email')
            ->missing('bills.data.0.auditLogs')
        );

    expect($open->fresh()->status)->toBe(BillStatus::Open);
});

it('shows sanitized paid Bill Payment and Receipt confirmation context', function () {
    $accountant = clearanceControllerAccountant();
    $patient = Patient::factory()->create();
    $bill = clearanceControllerPaidBill(125_050, $patient);

    $this->actingAs($accountant)
        ->get(route('billing.clearances.create', $bill))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/clearances/create')
            ->where('bill.id', $bill->id)
            ->where('bill.totalAmountMinor', 125_050)
            ->where('bill.payment.amountMinor', 125_050)
            ->where('bill.payment.receipt.receiptNumber', $bill->payment->receipt->receipt_number)
            ->where('bill.visit.nextStep', 'Awaiting consultation financial clearance')
            ->where('bill.patient.patientNumber', $patient->patient_number)
            ->missing('bill.patient.email')
            ->missing('bill.payment.recorded_by_user_id')
            ->missing('bill.auditLogs')
            ->missing('bill.checkedInAt')
        );
});

it('grants clearance and redirects to its immutable detail', function () {
    $accountant = clearanceControllerAccountant();
    $bill = clearanceControllerPaidBill(75_025);

    $response = $this->actingAs($accountant)
        ->post(route('billing.clearances.store', $bill));

    $financialClearance = FinancialClearance::query()->sole();

    $response
        ->assertRedirect(route('billing.clearances.show', $financialClearance))
        ->assertSessionHas(
            'status',
            "Financial clearance {$financialClearance->clearance_number} was granted.",
        );

    expect($financialClearance->grantedBy->is($accountant))->toBeTrue()
        ->and($bill->visit->fresh()->workflowMessage())->toBe('Awaiting Reception check-in')
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationFinancialCleared)->count())->toBe(1);
});

it('rejects client-controlled clearance and workflow fields', function () {
    $accountant = clearanceControllerAccountant();
    $bill = clearanceControllerPaidBill();

    $this->actingAs($accountant)
        ->post(route('billing.clearances.store', $bill), [
            'id' => 999_999,
            'bill_id' => 999_999,
            'visit_id' => 999_999,
            'patient_id' => 999_999,
            'clearance_number' => 'CLR-SUPPLIED',
            'granted_by_user_id' => 999_999,
            'granted_at' => '2020-01-01 00:00:00',
            'status' => 'checked_in',
            'is_cleared' => true,
            'checked_in_at' => now()->toIso8601String(),
        ])
        ->assertSessionHasErrors([
            'id',
            'bill_id',
            'visit_id',
            'patient_id',
            'clearance_number',
            'granted_by_user_id',
            'granted_at',
            'status',
            'is_cleared',
            'checked_in_at',
        ]);

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects ineligible missing open and procedure Bill URLs without audit', function () {
    $accountant = clearanceControllerAccountant();
    $openBill = clearanceControllerBillWithItem();
    $procedureBill = clearanceControllerPaidBill();
    DB::table('bills')->where('id', $procedureBill->id)->update([
        'type' => BillType::Procedure->value,
    ]);

    $this->actingAs($accountant)
        ->get(route('billing.clearances.create', 999_999))
        ->assertNotFound();
    $this->actingAs($accountant)
        ->post(route('billing.clearances.store', 999_999))
        ->assertNotFound();
    $this->actingAs($accountant)
        ->get(route('billing.clearances.create', $openBill))
        ->assertNotFound();
    $this->actingAs($accountant)
        ->post(route('billing.clearances.store', $openBill))
        ->assertSessionHasErrors('bill');
    $this->actingAs($accountant)
        ->get(route('billing.clearances.create', $procedureBill))
        ->assertNotFound();
    $this->actingAs($accountant)
        ->post(route('billing.clearances.store', $procedureBill))
        ->assertSessionHasErrors('bill');

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects duplicate clearance without duplicate records or audit', function () {
    $accountant = clearanceControllerAccountant();
    $bill = clearanceControllerPaidBill();

    $this->actingAs($accountant)
        ->post(route('billing.clearances.store', $bill))
        ->assertRedirect();
    $this->actingAs($accountant)
        ->post(route('billing.clearances.store', $bill))
        ->assertSessionHasErrors([
            'bill' => 'This consultation Bill has already received financial clearance.',
        ]);

    expect(FinancialClearance::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationFinancialCleared)->count())->toBe(1);
});

it('renders sanitized clearance detail and removes it from the pending queue', function () {
    $accountant = clearanceControllerAccountant();
    $patient = Patient::factory()->create();
    $bill = clearanceControllerPaidBill(125_050, $patient);
    $financialClearance = app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $bill,
    );

    $this->actingAs($accountant)
        ->get(route('billing.clearances.show', $financialClearance))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/clearances/show')
            ->where('clearance.clearanceNumber', $financialClearance->clearance_number)
            ->where('clearance.grantedBy', $accountant->name)
            ->where('clearance.bill.billNumber', $bill->bill_number)
            ->where('clearance.payment.amountMinor', 125_050)
            ->where('clearance.visit.status', ['value' => 'created', 'label' => 'Created'])
            ->where('clearance.visit.nextStep', 'Awaiting Reception check-in')
            ->where('clearance.patient.patientNumber', $patient->patient_number)
            ->missing('clearance.granted_by_user_id')
            ->missing('clearance.patient.email')
            ->missing('clearance.auditLogs')
            ->missing('clearance.checkedInAt')
        );

    $this->actingAs($accountant)
        ->get(route('billing.clearances.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bills.data', [])
            ->where('bills.pagination.total', 0)
        );
});

it('projects cleared workflow consistently without changing Visit status or checking in', function () {
    $accountant = clearanceControllerAccountant();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $visit = Visit::factory()->for($patient)->create([
        'appointment_id' => $appointment->id,
    ]);
    $bill = clearanceControllerPaidBill(125_050, $patient, [], $visit);
    $receipt = $bill->payment->receipt;
    $financialClearance = app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $bill,
    );

    $this->actingAs($accountant)
        ->get(route('billing.bills.show', $bill))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bill.financialClearance', [
                'id' => $financialClearance->id,
                'clearanceNumber' => $financialClearance->clearance_number,
                'grantedAt' => $financialClearance->granted_at->toIso8601String(),
            ])
            ->where('bill.visit.nextStep', 'Awaiting Reception check-in')
            ->missing('bill.checkIn')
        );

    $this->actingAs($accountant)
        ->get(route('billing.receipts.show', $receipt))
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.bill.financialClearance.clearanceNumber', $financialClearance->clearance_number)
            ->where('receipt.visit.nextStep', 'Awaiting Reception check-in')
            ->missing('receipt.visit.checkedInAt')
        );

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.status', ['value' => 'created', 'label' => 'Created'])
            ->where('visit.consultationBill.isFinanciallyCleared', true)
            ->where('visit.nextStep', 'Awaiting Reception check-in')
            ->missing('visit.financialClearance')
            ->missing('visit.checkedInAt')
        );

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.nextStep', 'Awaiting Reception check-in')
            ->missing('visitHistory.data.0.financialClearance')
        );

    $this->actingAs($receptionist)
        ->get(route('appointments.show', $appointment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointment.linkedVisit.nextStep', 'Awaiting Reception check-in')
            ->missing('appointment.linkedVisit.financialClearance')
        );

    expect($visit->fresh()->status->value)->toBe('created')
        ->and(Route::has('visits.check-in'))->toBeFalse();
});

it('does not automatically clear a Bill when payment succeeds', function () {
    $accountant = clearanceControllerAccountant();
    $bill = clearanceControllerBillWithItem();

    app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::Cash,
    );

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and($bill->visit->workflowMessage())->toBe('Awaiting consultation financial clearance')
        ->and(AuditLog::query()->where('action', AuditAction::ConsultationFinancialCleared)->count())->toBe(0);
});

it('redirects guests from every financial-clearance endpoint', function () {
    $bill = clearanceControllerPaidBill();
    $financialClearance = FinancialClearance::factory()->for($bill)->create();

    $this->get(route('billing.clearances.index'))->assertRedirect(route('login'));
    $this->get(route('billing.clearances.create', $bill))->assertRedirect(route('login'));
    $this->post(route('billing.clearances.store', $bill))->assertRedirect(route('login'));
    $this->get(route('billing.clearances.show', $financialClearance))->assertRedirect(route('login'));
});

it('denies every non-Accountant role from direct financial-clearance URLs', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $bill = clearanceControllerPaidBill();
    $financialClearance = FinancialClearance::factory()->for($bill)->create();

    $this->actingAs($actor)->get(route('billing.clearances.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('billing.clearances.create', $bill))->assertForbidden();
    $this->actingAs($actor)->post(route('billing.clearances.store', $bill))->assertForbidden();
    $this->actingAs($actor)->get(route('billing.clearances.show', $financialClearance))->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('logs out and denies an inactive Accountant', function () {
    $inactiveAccountant = User::factory()->forRole(StaffRole::Accountant)->inactive()->create();
    $bill = clearanceControllerPaidBill();

    $this->actingAs($inactiveAccountant)
        ->post(route('billing.clearances.store', $bill))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('exposes clearance routes without check-in mutation or deletion', function () {
    expect(Route::has('billing.clearances.index'))->toBeTrue()
        ->and(Route::has('billing.clearances.create'))->toBeTrue()
        ->and(Route::has('billing.clearances.store'))->toBeTrue()
        ->and(Route::has('billing.clearances.show'))->toBeTrue()
        ->and(Route::has('billing.clearances.destroy'))->toBeFalse()
        ->and(Route::has('visits.check-in'))->toBeFalse();
});

function clearanceControllerAccountant(): User
{
    return User::factory()->forRole(StaffRole::Accountant)->create();
}

/**
 * @param  array<string, mixed>  $billAttributes
 */
function clearanceControllerPaidBill(
    int $amountMinor = 50_000,
    ?Patient $patient = null,
    array $billAttributes = [],
    ?Visit $visit = null,
): Bill {
    $bill = clearanceControllerBillWithItem($amountMinor, $patient, $billAttributes, $visit);
    $payment = Payment::factory()->for($bill)->create();

    Receipt::factory()->for($payment)->create();
    $bill->status = BillStatus::Paid;
    $bill->save();

    return $bill->fresh(['payment.receipt', 'visit']);
}

/**
 * @param  array<string, mixed>  $billAttributes
 */
function clearanceControllerBillWithItem(
    int $amountMinor = 50_000,
    ?Patient $patient = null,
    array $billAttributes = [],
    ?Visit $visit = null,
): Bill {
    $patient ??= Patient::factory()->create();
    $visit ??= Visit::factory()->for($patient)->create();
    $bill = Bill::factory()->for($visit)->create($billAttributes);
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => $amountMinor,
    ]);
    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    return $bill;
}
