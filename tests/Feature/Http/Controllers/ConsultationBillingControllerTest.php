<?php

use App\AuditAction;
use App\BillType;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only unbilled created Visits in deterministic queue order', function () {
    $accountant = consultationBillingAccountant();
    ServiceCatalogItem::factory()->create();
    $patient = Patient::factory()->create();
    $oldest = Visit::factory()->for($patient)->create([
        'occurred_at' => '2026-09-01 08:00:00',
    ]);
    $newest = Visit::factory()->for($patient)->create([
        'occurred_at' => '2026-09-01 10:00:00',
    ]);
    $consultationBilled = Visit::factory()->for($patient)->create([
        'occurred_at' => '2026-09-01 07:00:00',
    ]);
    Bill::factory()->for($consultationBilled)->create();

    $this->actingAs($accountant)
        ->get(route('billing.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/consultations/index')
            ->where('visits.data', fn ($visits): bool => collect($visits)->pluck('id')->all() === [
                $oldest->id,
                $newest->id,
            ])
            ->where('visits.pagination.total', 2)
            ->where('visits.data.0.patient.patientNumber', $patient->patient_number)
            ->where('visits.data.0.status', ['value' => 'created', 'label' => 'Created'])
        );
});

it('exposes only active consultation services in deterministic order', function () {
    $accountant = consultationBillingAccountant();
    ServiceCatalogItem::factory()->create([
        'name' => 'Standard consultation',
        'unit_price_minor' => 150_000,
    ]);
    ServiceCatalogItem::factory()->create([
        'name' => 'Initial consultation',
        'unit_price_minor' => 200_000,
    ]);
    ServiceCatalogItem::factory()->inactive()->create([
        'name' => 'Inactive consultation',
    ]);
    ServiceCatalogItem::factory()->procedure()->create([
        'name' => 'Endoscopy procedure',
    ]);
    $visit = Visit::factory()->create();

    $this->actingAs($accountant)
        ->get(route('billing.consultations.create', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/consultations/create')
            ->has('consultationServices', 2)
            ->where('consultationServices.0.name', 'Initial consultation')
            ->where('consultationServices.0.unitPriceMinor', 200_000)
            ->where('consultationServices.1.name', 'Standard consultation')
            ->missing('consultationServices.0.category')
            ->missing('consultationServices.0.is_active')
        );
});

it('shows an operational no-service state without allowing malformed billing', function () {
    $accountant = consultationBillingAccountant();
    $visit = Visit::factory()->create();
    ServiceCatalogItem::factory()->inactive()->create();
    ServiceCatalogItem::factory()->procedure()->create();

    $this->actingAs($accountant)
        ->get(route('billing.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/consultations/index')
            ->has('consultationServices', 0)
            ->has('visits.data', 1)
        );

    $this->actingAs($accountant)
        ->get(route('billing.consultations.create', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/consultations/create')
            ->has('consultationServices', 0)
            ->where('visit.id', $visit->id)
        );

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [])
        ->assertSessionHasErrors([
            'service_catalog_item_id' => 'Select a consultation service.',
        ]);

    expect(Bill::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('creates a consultation Bill through the Accountant workflow', function () {
    $accountant = consultationBillingAccountant();
    $patient = Patient::factory()->create();
    $visit = Visit::factory()->for($patient)->create();
    $service = ServiceCatalogItem::factory()->create([
        'name' => 'Consultation review',
        'unit_price_minor' => 75_025,
    ]);

    $response = $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ]);

    $bill = Bill::query()->sole();
    $response
        ->assertRedirect(route('billing.bills.show', $bill))
        ->assertSessionHas('status', "Consultation Bill {$bill->bill_number} was created.");

    expect($bill->visit->is($visit))->toBeTrue()
        ->and($bill->type)->toBe(BillType::Consultation)
        ->and($bill->items()->sole()->description)->toBe('Consultation review')
        ->and($bill->items()->sole()->amount_minor)->toBe(75_025)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Created)
        ->and(AuditLog::query()->where('action', AuditAction::BillCreated)->count())->toBe(1);
});

it('rejects server-controlled Bill fields from normal request input', function () {
    $accountant = consultationBillingAccountant();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
            'patient_id' => 999_999,
            'visit_id' => 999_999,
            'bill_number' => 'BIL-SUPPLIED',
            'type' => BillType::Procedure->value,
            'status' => 'paid',
            'amount_minor' => 1,
            'description' => 'Supplied description',
        ])
        ->assertSessionHasErrors([
            'patient_id',
            'visit_id',
            'bill_number',
            'type',
            'status',
            'amount_minor',
            'description',
        ]);

    expect(Bill::query()->count())->toBe(0)
        ->and(BillItem::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects inactive and procedure services at the HTTP boundary', function (array $attributes) {
    $accountant = consultationBillingAccountant();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create($attributes);

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ])
        ->assertSessionHasErrors([
            'service_catalog_item_id' => 'Select an active consultation service.',
        ]);

    expect(Bill::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'inactive consultation service' => [['is_active' => false]],
    'procedure service' => [['category' => BillType::Procedure]],
]);

it('rejects duplicate requests without extra financial or audit records', function () {
    $accountant = consultationBillingAccountant();
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ])
        ->assertRedirect();

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ])
        ->assertSessionHasErrors([
            'visit' => 'A consultation Bill already exists for this Visit.',
        ]);

    expect(Bill::query()->count())->toBe(1)
        ->and(BillItem::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::BillCreated)->count())->toBe(1);
});

it('removes billed Visits from the queue and advances derived workflow messaging', function () {
    $accountant = consultationBillingAccountant();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->for($patient)->create();
    $visit = Visit::factory()->for($patient)->create([
        'appointment_id' => $appointment->id,
    ]);
    $service = ServiceCatalogItem::factory()->create([
        'unit_price_minor' => 125_000,
    ]);

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.nextStep', 'Awaiting consultation billing')
            ->where('visit.consultationBill', null)
        );

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ])
        ->assertRedirect();

    $bill = Bill::query()->sole();

    $this->actingAs($accountant)
        ->get(route('billing.consultations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('visits.data', 0)
            ->where('visits.pagination.total', 0)
        );

    $this->actingAs($receptionist)
        ->get(route('visits.show', $visit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visit.status', ['value' => 'created', 'label' => 'Created'])
            ->where('visit.nextStep', 'Awaiting consultation payment')
            ->where('visit.consultationBill.billNumber', $bill->bill_number)
            ->where('visit.consultationBill.totalAmountMinor', 125_000)
            ->missing('visit.payments')
            ->missing('visit.clearance')
            ->where('visit.checkIn', null)
        );

    $this->actingAs($receptionist)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn (Assert $page) => $page
            ->where('visitHistory.data.0.nextStep', 'Awaiting consultation payment')
            ->missing('visitHistory.data.0.consultationBill')
        );

    $this->actingAs($receptionist)
        ->get(route('appointments.show', $appointment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('appointment.linkedVisit.nextStep', 'Awaiting consultation payment')
            ->missing('appointment.linkedVisit.bill')
        );
});

it('renders a sanitized consultation Bill detail with no later-stage controls', function () {
    $accountant = consultationBillingAccountant();
    $patient = Patient::factory()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Wanjiku',
        'last_name' => 'Kamau',
    ]);
    $visit = Visit::factory()->for($patient)->create();
    $service = ServiceCatalogItem::factory()->create([
        'name' => 'Initial consultation',
        'unit_price_minor' => 100_050,
    ]);

    $this->actingAs($accountant)
        ->post(route('billing.consultations.store', $visit), [
            'service_catalog_item_id' => $service->id,
        ])
        ->assertRedirect();

    $bill = Bill::query()->sole();

    $this->actingAs($accountant)
        ->get(route('billing.bills.show', $bill))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/show')
            ->where('bill.billNumber', $bill->bill_number)
            ->where('bill.patient', [
                'patientNumber' => $patient->patient_number,
                'name' => 'Amina Wanjiku Kamau',
            ])
            ->where('bill.visit.visitNumber', $visit->visit_number)
            ->where('bill.visit.nextStep', 'Awaiting consultation payment')
            ->where('bill.type', ['value' => 'consultation', 'label' => 'Consultation'])
            ->where('bill.status', ['value' => 'open', 'label' => 'Open'])
            ->where('bill.totalAmountMinor', 100_050)
            ->has('bill.items', 1)
            ->where('bill.items.0.description', 'Initial consultation')
            ->where('bill.items.0.amountMinor', 100_050)
            ->where('bill.payment', null)
            ->missing('bill.visit.patient_id')
            ->missing('bill.patient.id')
            ->missing('bill.payments')
            ->missing('bill.receipts')
            ->missing('bill.clearance')
            ->missing('bill.checkIn')
            ->missing('bill.clinical')
        );
});

it('protects every consultation billing endpoint from guests and non-Accountant roles', function (StaffRole $role) {
    $visit = Visit::factory()->create();
    $service = ServiceCatalogItem::factory()->create();
    $bill = Bill::factory()->for($visit)->create();

    $this->get(route('billing.consultations.index'))->assertRedirect(route('login'));
    $this->get(route('billing.consultations.create', $visit))->assertRedirect(route('login'));
    $this->post(route('billing.consultations.store', $visit), [
        'service_catalog_item_id' => $service->id,
    ])->assertRedirect(route('login'));
    $this->get(route('billing.bills.show', $bill))->assertRedirect(route('login'));

    $actor = User::factory()->forRole($role)->create();

    $this->actingAs($actor)->get(route('billing.consultations.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('billing.consultations.create', $visit))->assertForbidden();
    $this->actingAs($actor)->post(route('billing.consultations.store', $visit), [
        'service_catalog_item_id' => $service->id,
    ])->assertForbidden();
    $this->actingAs($actor)->get(route('billing.bills.show', $bill))->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('redirects direct confirmation of an already billed Visit to its Bill detail', function () {
    $accountant = consultationBillingAccountant();
    $visit = Visit::factory()->create();
    $bill = Bill::factory()->for($visit)->create();

    $this->actingAs($accountant)
        ->get(route('billing.consultations.create', $visit))
        ->assertRedirect(route('billing.bills.show', $bill));
});

it('exposes both financial gates while keeping check-in out of billing', function () {
    $accountant = consultationBillingAccountant();
    $procedureBill = Bill::factory()->procedure()->create();

    $this->actingAs($accountant)
        ->get(route('billing.bills.show', $procedureBill))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/show')
            ->where('bill.type', ['value' => 'procedure', 'label' => 'Procedure'])
        );

    expect(Route::has('billing.payments.store'))->toBeTrue()
        ->and(Route::has('billing.receipts.show'))->toBeTrue()
        ->and(Route::has('billing.clearances.store'))->toBeTrue()
        ->and(Route::has('visits.check-in'))->toBeFalse()
        ->and(Route::has('billing.procedures.store'))->toBeTrue();
});

function consultationBillingAccountant(): User
{
    return User::factory()->forRole(StaffRole::Accountant)->create();
}
