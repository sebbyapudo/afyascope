<?php

use App\Actions\Billing\CreateProcedureBill;
use App\AuditAction;
use App\BillType;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\User;
use App\StaffRole;
use Inertia\Testing\AssertableInertia as Assert;

it('shows only authoritative unbilled procedure-required handoffs oldest first', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();

    $this->travelTo('2026-09-04 08:00:00');
    $oldest = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $this->travelTo('2026-09-04 09:00:00');
    $billed = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    app(CreateProcedureBill::class)->handle($accountant, $billed);
    $this->travelTo('2026-09-04 10:00:00');
    $newest = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    ProcedureDecision::factory()->createAuthoritativeDecisionFixture([
        'clinical_rationale' => 'No procedure is required.',
    ]);
    $this->travelBack();

    $this->actingAs($accountant)
        ->get(route('billing.procedures.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/procedures/index')
            ->where('handoffs.data', fn ($handoffs): bool => collect($handoffs)->pluck('id')->all() === [
                $oldest->id,
                $newest->id,
            ])
            ->where('handoffs.pagination.total', 2)
            ->where('handoffs.data.0.handoffNumber', $oldest->handoff_number)
            ->where('handoffs.data.0.decisionNumber', $oldest->procedureDecision->decision_number)
            ->where('handoffs.data.0.procedure.name', $oldest->serviceCatalogItem->name)
            ->where('handoffs.data.0.procedure.amountMinor', $oldest->serviceCatalogItem->unit_price_minor)
            ->where('handoffs.data.0.visit.nextStep', 'Awaiting procedure billing')
            ->missing('handoffs.data.0.procedure.unit_price_minor')
            ->missing('handoffs.data.0.clinicalRationale')
            ->missing('handoffs.data.0.doctor.email')
        );
});

it('shows sanitized confirmation and creates a procedure Bill from the bound handoff', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

    $this->actingAs($accountant)
        ->get(route('billing.procedures.create', $handoff))
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/procedures/create')
            ->where('handoff.id', $handoff->id)
            ->where('handoff.handoffNumber', $handoff->handoff_number)
            ->where('handoff.patient.patientNumber', $handoff->visit->patient->patient_number)
            ->where('handoff.procedure.name', $handoff->serviceCatalogItem->name)
            ->missing('handoff.clinicalRationale')
            ->missing('handoff.procedure.service_catalog_item_id')
        );

    $response = $this->actingAs($accountant)
        ->post(route('billing.procedures.store', $handoff));

    $bill = Bill::query()->where('type', BillType::Procedure)->sole();

    $response
        ->assertRedirect(route('billing.bills.show', $bill))
        ->assertSessionHas('status', "Procedure Bill {$bill->bill_number} was created.");

    expect($bill->procedure_billing_handoff_id)->toBe($handoff->id)
        ->and($bill->items()->sole()->description)->toBe($handoff->serviceCatalogItem->name)
        ->and($bill->items()->sole()->amount_minor)->toBe($handoff->serviceCatalogItem->unit_price_minor)
        ->and($handoff->visit->fresh()->workflowMessage())->toBe('Awaiting procedure payment')
        ->and(AuditLog::query()->where('action', AuditAction::BillCreated)->count())->toBe(1);
});

it('rejects forged procedure Bill fields from normal input', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $billItemCount = BillItem::query()->count();

    $this->actingAs($accountant)
        ->post(route('billing.procedures.store', $handoff), [
            'visit_id' => 999_999,
            'procedure_billing_handoff_id' => 999_999,
            'procedure_decision_id' => 999_999,
            'service_catalog_item_id' => 999_999,
            'bill_number' => 'BIL-SUPPLIED',
            'type' => BillType::Consultation->value,
            'status' => 'paid',
            'amount_minor' => 1,
            'description' => 'Forged charge',
            'doctor_user_id' => 999_999,
        ])
        ->assertSessionHasErrors([
            'visit_id',
            'procedure_billing_handoff_id',
            'procedure_decision_id',
            'service_catalog_item_id',
            'bill_number',
            'type',
            'status',
            'amount_minor',
            'description',
            'doctor_user_id',
        ]);

    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(0)
        ->and(BillItem::query()->count())->toBe($billItemCount)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('redirects stale billed confirmation and excludes its handoff from the queue', function () {
    $accountant = User::factory()->forRole(StaffRole::Accountant)->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $bill = app(CreateProcedureBill::class)->handle($accountant, $handoff);

    $this->actingAs($accountant)
        ->get(route('billing.procedures.create', $handoff))
        ->assertRedirect(route('billing.bills.show', $bill));

    $this->actingAs($accountant)
        ->get(route('billing.procedures.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('handoffs.data', [])
            ->where('handoffs.pagination.total', 0)
        );
});

it('protects every procedure billing endpoint from guests and non-Accountant roles', function (StaffRole $role) {
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

    $this->get(route('billing.procedures.index'))->assertRedirect(route('login'));
    $this->get(route('billing.procedures.create', $handoff))->assertRedirect(route('login'));
    $this->post(route('billing.procedures.store', $handoff))->assertRedirect(route('login'));

    $actor = User::factory()->forRole($role)->create();

    $this->actingAs($actor)->get(route('billing.procedures.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('billing.procedures.create', $handoff))->assertForbidden();
    $this->actingAs($actor)->post(route('billing.procedures.store', $handoff))->assertForbidden();
})->with([
    StaffRole::Receptionist,
    StaffRole::Doctor,
    StaffRole::Nurse,
    StaffRole::Administrator,
    StaffRole::Management,
]);

it('logs out and denies an inactive Accountant', function () {
    $inactiveAccountant = User::factory()->forRole(StaffRole::Accountant)->inactive()->create();
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

    $this->actingAs($inactiveAccountant)
        ->post(route('billing.procedures.store', $handoff))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(Bill::query()->where('type', BillType::Procedure)->count())->toBe(0);
});
