<?php

use App\Actions\Billing\CreateConsultationBill;
use App\Actions\Billing\CreateProcedureBill;
use App\Actions\Billing\GrantConsultationFinancialClearance;
use App\Actions\Billing\RecordConsultationPayment;
use App\Actions\Visits\CheckInVisit;
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
use App\Models\ProcedureBillingHandoff;
use App\Models\Receipt;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\PaymentMethod;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('completes consultation Gate 1 with exact queue transitions projections and audits', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => null,
        'last_name' => 'Kamau',
    ]);
    $appointment = Appointment::factory()->for($patient)->create([
        'scheduled_at' => now()->subMinutes(15),
    ]);
    $consultationService = ServiceCatalogItem::factory()->create([
        'name' => 'Initial consultation',
        'unit_price_minor' => 125_050,
    ]);

    $this->actingAs($receptionist)
        ->get(route('appointments.index', ['awaiting_attendance' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointments.data', fn ($appointments): bool => collect($appointments)
                ->pluck('id')->contains($appointment->id))
        );

    $this->actingAs($receptionist)
        ->post(route('appointments.visit.store', $appointment))
        ->assertRedirect();

    $visit = Visit::query()->sole();

    expect(Patient::query()->count())->toBe(1)
        ->and($visit->patient->is($patient))->toBeTrue()
        ->and($visit->appointment?->is($appointment))->toBeTrue()
        ->and($appointment->fresh()->visit?->is($visit))->toBeTrue()
        ->and($visit->status)->toBe(VisitStatus::Created)
        ->and($visit->workflowMessage())->toBe('Awaiting consultation billing');

    $this->actingAs($receptionist)
        ->get(route('appointments.index', ['awaiting_attendance' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointments.data', fn ($appointments): bool => ! collect($appointments)
                ->pluck('id')->contains($appointment->id))
        );
    $this->actingAs($accountant)
        ->get(route('billing.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visits.data', fn ($visits): bool => collect($visits)
                ->pluck('id')->contains($visit->id))
        );
    $this->actingAs($accountant)
        ->get(route('billing.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('bills.data', 0));
    $this->actingAs($accountant)
        ->get(route('billing.clearances.index'))
        ->assertInertia(fn (Assert $page) => $page->has('bills.data', 0));
    $this->actingAs($receptionist)
        ->get(route('check-ins.index'))
        ->assertInertia(fn (Assert $page) => $page->has('visits.data', 0));

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $consultationService->id,
        ])
        ->assertRedirect();

    $bill = Bill::query()->sole();

    expect($bill->type)->toBe(BillType::Consultation)
        ->and($bill->status)->toBe(BillStatus::Open)
        ->and($bill->totalAmountMinor())->toBe(125_050)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting consultation payment');

    $this->actingAs($accountant)
        ->get(route('billing.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visits.data', fn ($visits): bool => ! collect($visits)
                ->pluck('id')->contains($visit->id))
        );
    $this->actingAs($accountant)
        ->get(route('billing.payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bills.data', fn ($bills): bool => collect($bills)
                ->pluck('id')->contains($bill->id))
        );

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::MobileMoney->value,
        ])
        ->assertRedirect();

    $payment = Payment::query()->sole();
    $receipt = Receipt::query()->sole();

    expect($payment->amount_minor)->toBe(125_050)
        ->and($payment->receipt?->is($receipt))->toBeTrue()
        ->and($bill->fresh()->status)->toBe(BillStatus::Paid)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Created)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting consultation financial clearance');

    $this->actingAs($accountant)
        ->get(route('billing.payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bills.data', fn ($bills): bool => ! collect($bills)
                ->pluck('id')->contains($bill->id))
        );
    $this->actingAs($accountant)
        ->get(route('billing.clearances.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bills.data', fn ($bills): bool => collect($bills)
                ->pluck('id')->contains($bill->id))
        );

    $this->actingAs($accountant)
        ->post(route('billing.clearances.store', $bill))
        ->assertRedirect();

    $clearance = FinancialClearance::query()->sole();

    expect($visit->fresh()->status)->toBe(VisitStatus::Created)
        ->and($visit->fresh()->workflowMessage())->toBe('Awaiting Reception check-in');

    $this->actingAs($accountant)
        ->get(route('billing.clearances.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bills.data', fn ($bills): bool => ! collect($bills)
                ->pluck('id')->contains($bill->id))
        );
    $this->actingAs($receptionist)
        ->get(route('check-ins.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visits.data', fn ($visits): bool => collect($visits)
                ->pluck('id')->contains($visit->id))
        );

    $this->actingAs($receptionist)
        ->post(route('check-ins.store', $visit))
        ->assertRedirect();

    $checkIn = VisitCheckIn::query()->sole();
    $visit->refresh();

    expect($visit->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->workflowMessage())->toBe('Ready for Doctor consultation')
        ->and($appointment->fresh()->visit?->is($visit))->toBeTrue()
        ->and(Patient::query()->count())->toBe(1);

    $this->actingAs($receptionist)
        ->get(route('check-ins.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visits.data', fn ($visits): bool => ! collect($visits)
                ->pluck('id')->contains($visit->id))
        );

    $financialAuditLogs = AuditLog::query()
        ->whereIn('action', [
            AuditAction::BillCreated->value,
            AuditAction::PaymentRecorded->value,
            AuditAction::ReceiptIssued->value,
            AuditAction::ConsultationFinancialCleared->value,
            AuditAction::VisitCheckedIn->value,
        ])
        ->orderBy('id')
        ->get();

    expect($financialAuditLogs->pluck('action')->all())->toBe([
        AuditAction::BillCreated,
        AuditAction::PaymentRecorded,
        AuditAction::ReceiptIssued,
        AuditAction::ConsultationFinancialCleared,
        AuditAction::VisitCheckedIn,
    ])->and($financialAuditLogs->pluck('actor_id')->all())->toBe([
        $accountant->id,
        $accountant->id,
        $accountant->id,
        $accountant->id,
        $receptionist->id,
    ]);

    $this->actingAs($receptionist)
        ->get(route('appointments.show', $appointment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointment.linkedVisit.id', $visit->id)
            ->where('appointment.linkedVisit.status.value', VisitStatus::CheckedIn->value)
            ->where('appointment.linkedVisit.nextStep', 'Ready for Doctor consultation')
            ->missing('appointment.linkedVisit.payment')
            ->missing('appointment.linkedVisit.auditLogs')
        );
    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.status.value', VisitStatus::CheckedIn->value)
            ->where('visit.nextStep', 'Ready for Doctor consultation')
            ->where('visit.consultationBill.status.value', BillStatus::Paid->value)
            ->where('visit.consultationBill.isFinanciallyCleared', true)
            ->where('visit.checkIn.id', $checkIn->id)
            ->missing('visit.payment')
            ->missing('visit.receipt')
            ->missing('visit.auditLogs')
        );
    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data', fn ($visits): bool => collect($visits)
                ->contains(fn (array $item): bool => $item['id'] === $visit->id
                    && $item['status']['value'] === VisitStatus::CheckedIn->value
                    && $item['nextStep'] === 'Ready for Doctor consultation'))
            ->missing('patient.billing')
            ->missing('patient.payments')
            ->missing('patient.auditLogs')
        );
    $this->actingAs($accountant)
        ->get(route('billing.bills.show', $bill))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bill.visit.status.value', VisitStatus::CheckedIn->value)
            ->where('bill.visit.nextStep', 'Ready for Doctor consultation')
            ->where('bill.payment.paymentNumber', $payment->payment_number)
            ->where('bill.payment.receipt.receiptNumber', $receipt->receipt_number)
            ->where('bill.financialClearance.clearanceNumber', $clearance->clearance_number)
        );
    $this->actingAs($accountant)
        ->get(route('billing.receipts.show', $receipt))
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.visit.status.value', VisitStatus::CheckedIn->value)
            ->where('receipt.visit.nextStep', 'Ready for Doctor consultation')
            ->where('receipt.bill.financialClearance.clearanceNumber', $clearance->clearance_number)
        );
    $this->actingAs($accountant)
        ->get(route('billing.clearances.show', $clearance))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clearance.visit.status.value', VisitStatus::CheckedIn->value)
            ->where('clearance.visit.nextStep', 'Ready for Doctor consultation')
            ->where('clearance.payment.receipt.receiptNumber', $receipt->receipt_number)
        );
    $this->actingAs($receptionist)
        ->get(route('check-ins.show', $checkIn))
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkIn.visit.status.value', VisitStatus::CheckedIn->value)
            ->where('checkIn.visit.nextStep', 'Ready for Doctor consultation')
            ->where('checkIn.clearance.clearanceNumber', $clearance->clearance_number)
        );
});

it('prevents duplicate Gate 1 records without duplicate audits', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();
    $bill = app(CreateConsultationBill::class)->handle($accountant, $visit, $service);
    app(RecordConsultationPayment::class)->handle($accountant, $bill, PaymentMethod::Cash);
    app(GrantConsultationFinancialClearance::class)->handle($accountant, $bill);
    app(CheckInVisit::class)->handle($receptionist, $visit);
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(CreateConsultationBill::class)->handle(
        $accountant,
        $visit,
        $service,
    ))->toThrow(ValidationException::class);
    expect(fn () => app(RecordConsultationPayment::class)->handle(
        $accountant,
        $bill,
        PaymentMethod::Cash,
    ))->toThrow(ValidationException::class);
    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $bill,
    ))->toThrow(ValidationException::class);
    expect(fn () => app(CheckInVisit::class)->handle(
        $receptionist,
        $visit,
    ))->toThrow(ValidationException::class);

    expect(Bill::query()->count())->toBe(1)
        ->and(BillItem::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and(Receipt::query()->count())->toBe(1)
        ->and(FinancialClearance::query()->count())->toBe(1)
        ->and(VisitCheckIn::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('keeps consultation payment amount entirely server controlled for :dataset input', function (int $amountMinor) {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $bill = phaseThreeAcceptanceBillWithItem(amountMinor: 125_050);

    $this->actingAs($accountant)
        ->post(route('billing.payments.store', $bill), [
            'payment_method' => PaymentMethod::Cash->value,
            'amount_minor' => $amountMinor,
        ])
        ->assertSessionHasErrors('amount_minor');

    expect(Payment::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and($bill->fresh()->status)->toBe(BillStatus::Open)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'underpayment' => 125_049,
    'client-supplied exact amount' => 125_050,
    'overpayment' => 125_051,
]);

it('rejects payment clearance and check-in attempts that bypass their prerequisites', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $unbilledVisit = Visit::factory()->create();

    expect(fn () => app(RecordConsultationPayment::class)->handle(
        $accountant,
        new Bill,
        PaymentMethod::Cash,
    ))->toThrow(ValidationException::class);

    $openBill = phaseThreeAcceptanceBillWithItem();

    expect(fn () => app(GrantConsultationFinancialClearance::class)->handle(
        $accountant,
        $openBill,
    ))->toThrow(ValidationException::class);
    expect(fn () => app(CheckInVisit::class)->handle(
        $receptionist,
        $unbilledVisit,
    ))->toThrow(ValidationException::class);

    $paidBill = phaseThreeAcceptanceBillWithItem();
    app(RecordConsultationPayment::class)->handle($accountant, $paidBill, PaymentMethod::Cash);
    $auditCount = AuditLog::query()->count();

    expect(fn () => app(CheckInVisit::class)->handle(
        $receptionist,
        $paidBill->visit,
    ))->toThrow(ValidationException::class);

    expect(FinancialClearance::query()->count())->toBe(0)
        ->and(VisitCheckIn::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

it('separates Reception financial actions from Accountant check-in', function () {
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();
    $openBill = phaseThreeAcceptanceBillWithItem();
    $paidBill = phaseThreeAcceptancePaidBill();
    $eligibleVisit = FinancialClearance::factory()->create()->bill->visit;

    $this->actingAs($receptionist)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ])
        ->assertForbidden();
    $this->actingAs($receptionist)
        ->post(route('billing.payments.store', $openBill), [
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->assertForbidden();
    $this->actingAs($receptionist)
        ->post(route('billing.clearances.store', $paidBill))
        ->assertForbidden();
    $this->actingAs($accountant)
        ->post(route('check-ins.store', $eligibleVisit))
        ->assertForbidden();
});

it('denies routine Gate 1 operations to the :dataset role', function (StaffRole $role) {
    $actor = User::factory()->forRole($role)->create();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();
    $openBill = phaseThreeAcceptanceBillWithItem();
    $paidBill = phaseThreeAcceptancePaidBill();
    $eligibleVisit = FinancialClearance::factory()->create()->bill->visit;

    $this->actingAs($actor)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ])
        ->assertForbidden();
    $this->actingAs($actor)
        ->post(route('billing.payments.store', $openBill), [
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->assertForbidden();
    $this->actingAs($actor)
        ->post(route('billing.clearances.store', $paidBill))
        ->assertForbidden();
    $this->actingAs($actor)
        ->post(route('check-ins.store', $eligibleVisit))
        ->assertForbidden();
})->with([
    'Doctor' => StaffRole::Doctor,
    'Nurse' => StaffRole::Nurse,
    'Administrator' => StaffRole::Administrator,
    'Management' => StaffRole::Management,
]);

it('denies Gate 1 operations to inactive staff and redirects guests', function () {
    $inactiveAccountant = User::factory()->forRole(StaffRole::Accountant)->inactive()->create();
    $inactiveReceptionist = User::factory()->forRole(StaffRole::Receptionist)->inactive()->create();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();
    $openBill = phaseThreeAcceptanceBillWithItem();
    $paidBill = phaseThreeAcceptancePaidBill();
    $eligibleVisit = FinancialClearance::factory()->create()->bill->visit;

    $this->actingAs($inactiveAccountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ])
        ->assertRedirect(route('login'));
    $this->actingAs($inactiveAccountant)
        ->post(route('billing.payments.store', $openBill), [
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->assertRedirect(route('login'));
    $this->actingAs($inactiveAccountant)
        ->post(route('billing.clearances.store', $paidBill))
        ->assertRedirect(route('login'));
    $this->actingAs($inactiveReceptionist)
        ->post(route('check-ins.store', $eligibleVisit))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->post(route('billing.consultations.store', $visit), [
        'service_catalog_item_id' => $service->id,
    ])->assertRedirect(route('login'));
    $this->post(route('billing.payments.store', $openBill), [
        'payment_method' => PaymentMethod::Cash->value,
    ])->assertRedirect(route('login'));
    $this->post(route('billing.clearances.store', $paidBill))
        ->assertRedirect(route('login'));
    $this->post(route('check-ins.store', $eligibleVisit))
        ->assertRedirect(route('login'));
});

it('keeps procedure billing dependent on one authoritative Doctor handoff', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $checkedInVisit = VisitCheckIn::factory()->create()->visit;
    $procedureService = ServiceCatalogItem::factory()->procedure()->create();
    $directHandoff = new ProcedureBillingHandoff;
    $directHandoff->visit()->associate($checkedInVisit);
    $directHandoff->serviceCatalogItem()->associate($procedureService);
    $directHandoff->decidedBy()->associate($doctor);

    expect($checkedInVisit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($checkedInVisit->fresh()->procedureBillingHandoff)->toBeNull()
        ->and($checkedInVisit->fresh()->procedureBill)->toBeNull()
        ->and(ProcedureBillingHandoff::query()->awaitingBill()->count())->toBe(0);

    expect(fn () => $directHandoff->save())->toThrow(
        LogicException::class,
        'Procedure billing handoffs are reserved for the future authoritative Doctor procedure-decision workflow.',
    );
    expect(fn () => app(CreateProcedureBill::class)->handle(
        $accountant,
        $directHandoff,
    ))->toThrow(ValidationException::class);

    $authoritativeHandoff = ProcedureBillingHandoff::factory()
        ->for($procedureService, 'serviceCatalogItem')
        ->createAuthoritativeDecisionFixture();

    expect(ProcedureBillingHandoff::query()->awaitingBill()->pluck('id')->all())
        ->toBe([$authoritativeHandoff->id]);

    $procedureBill = app(CreateProcedureBill::class)->handle($accountant, $authoritativeHandoff);

    expect($procedureBill->type)->toBe(BillType::Procedure)
        ->and($procedureBill->procedure_billing_handoff_id)->toBe($authoritativeHandoff->id)
        ->and(ProcedureBillingHandoff::query()->awaitingBill()->count())->toBe(0)
        ->and(Route::has('billing.procedures.index'))->toBeFalse()
        ->and(Route::has('billing.procedures.store'))->toBeFalse()
        ->and(Route::has('consultations.store'))->toBeFalse()
        ->and(Route::has('billing.procedure-payments.store'))->toBeFalse()
        ->and(Route::has('billing.procedure-clearances.store'))->toBeFalse();
});

it('rejects cross-category billing services without financial or audit residue', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $visit = Visit::factory()->create();
    $procedureService = ServiceCatalogItem::factory()->procedure()->create();

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $procedureService->id,
        ])
        ->assertSessionHasErrors('service_catalog_item_id');

    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $handoff->serviceCatalogItem->update(['category' => BillType::Consultation]);
    $billItemCount = BillItem::query()->count();

    expect(fn () => app(CreateProcedureBill::class)->handle(
        $accountant,
        $handoff,
    ))->toThrow(ValidationException::class);

    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(BillItem::query()->count())->toBe($billItemCount)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('enforces one handoff and one Bill per Visit and type at the database boundary', function () {
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

    expect(fn () => ProcedureBillingHandoff::factory()
        ->for($handoff->visit)
        ->createAuthoritativeDecisionFixture())->toThrow(QueryException::class);

    $visit = Visit::factory()->create();
    Bill::factory()->for($visit)->create();

    expect(fn () => Bill::factory()->for($visit)->create())->toThrow(QueryException::class);

    expect(ProcedureBillingHandoff::query()->where('visit_id', $handoff->visit_id)->count())->toBe(1)
        ->and(Bill::query()->where('visit_id', $visit->id)->where('type', BillType::Consultation)->count())->toBe(1);
});

function phaseThreeAcceptanceBillWithItem(
    ?Visit $visit = null,
    int $amountMinor = 50_000,
): Bill {
    $bill = Bill::factory()->for($visit ?? Visit::factory()->create())->create();
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => $amountMinor,
    ]);

    BillItem::factory()->for($bill)->for($service, 'serviceCatalogItem')->create();

    return $bill->fresh(['items', 'visit']);
}

function phaseThreeAcceptancePaidBill(int $amountMinor = 50_000): Bill
{
    $bill = phaseThreeAcceptanceBillWithItem(amountMinor: $amountMinor);
    $payment = Payment::factory()->for($bill)->create();

    Receipt::factory()->for($payment)->create();
    $bill->status = BillStatus::Paid;
    $bill->save();

    return $bill->fresh(['items', 'payment.receipt', 'visit']);
}
