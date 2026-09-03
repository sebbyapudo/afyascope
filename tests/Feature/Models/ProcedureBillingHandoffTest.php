<?php

use App\BillType;
use App\Models\Bill;
use App\Models\ProcedureBillingHandoff;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('represents an immutable server-identified authoritative Doctor decision fixture', function () {
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture([
        'handoff_number' => 'PBH-SUPPLIED',
        'decided_at' => '2020-01-01 00:00:00',
    ]);

    expect($handoff->handoff_number)->toMatch('/^PBH-\d{6,}$/')
        ->and($handoff->visit->status)->toBe(VisitStatus::CheckedIn)
        ->and($handoff->serviceCatalogItem->category)->toBe(BillType::Procedure)
        ->and($handoff->decidedBy->role->slug)->toBe(StaffRole::Doctor->value)
        ->and($handoff->decided_at->equalTo($handoff->created_at))->toBeTrue();

    $handoff->serviceCatalogItem()->associate(ServiceCatalogItem::factory()->procedure()->create());

    expect(fn () => $handoff->save())->toThrow(LogicException::class)
        ->and($handoff->fresh()->handoff_number)->not->toBe('PBH-SUPPLIED');
});

it('rejects direct handoff persistence even when the Visit is checked in', function () {
    $checkedInVisit = VisitCheckIn::factory()->create()->visit;
    $procedureService = ServiceCatalogItem::factory()->procedure()->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $handoff = new ProcedureBillingHandoff;
    $handoff->visit()->associate($checkedInVisit);
    $handoff->serviceCatalogItem()->associate($procedureService);
    $handoff->decidedBy()->associate($doctor);

    expect(fn () => $handoff->save())->toThrow(
        LogicException::class,
        'Procedure billing handoffs require the authoritative Doctor procedure-decision workflow.',
    );

    expect(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and($checkedInVisit->fresh()->workflowMessage())->toBe('Ready for Doctor consultation');
});

it('enforces one procedure billing handoff per Visit at the database boundary', function () {
    $handoff = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

    expect(fn () => ProcedureBillingHandoff::factory()
        ->for($handoff->visit)
        ->createAuthoritativeDecisionFixture())->toThrow(QueryException::class);

    expect(ProcedureBillingHandoff::query()->where('visit_id', $handoff->visit_id)->count())->toBe(1);
});

it('returns only unbilled handoffs in deterministic oldest-first queue order', function () {
    $oldest = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $oldest->getConnection()->table('procedure_billing_handoffs')
        ->where('id', $oldest->id)
        ->update(['decided_at' => '2026-09-02 08:00:00']);
    $billed = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $billed->getConnection()->table('procedure_billing_handoffs')
        ->where('id', $billed->id)
        ->update(['decided_at' => '2026-09-02 09:00:00']);
    $newest = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $newest->getConnection()->table('procedure_billing_handoffs')
        ->where('id', $newest->id)
        ->update(['decided_at' => '2026-09-02 10:00:00']);
    $inactive = ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();
    $inactive->serviceCatalogItem->update(['is_active' => false]);
    Bill::factory()->procedure($billed)->create();

    expect(ProcedureBillingHandoff::query()->awaitingBill()->pluck('id')->all())->toBe([
        $oldest->id,
        $newest->id,
    ]);
});

it('does not fabricate procedure workflow from check-in alone', function () {
    $visit = VisitCheckIn::factory()->create()->visit;

    expect($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->fresh()->workflowMessage())->toBe('Ready for Doctor consultation')
        ->and($visit->fresh()->procedureBillingHandoff)->toBeNull()
        ->and($visit->fresh()->procedureBill)->toBeNull();
});

it('requires a legitimate handoff for model-created procedure Bills and keeps consultation Bills separate', function () {
    $visit = Visit::factory()->create();
    $procedureBill = new Bill;
    $procedureBill->visit()->associate($visit);
    $procedureBill->type = BillType::Procedure;

    expect(fn () => $procedureBill->save())->toThrow(LogicException::class);

    $consultationBill = Bill::factory()->for($visit)->create();

    expect($consultationBill->procedure_billing_handoff_id)->toBeNull()
        ->and($visit->fresh()->consultationBill->is($consultationBill))->toBeTrue()
        ->and($visit->fresh()->procedureBill)->toBeNull();
});

it('adds only the structural foundation without procedure or clinical routes', function () {
    expect(Schema::hasTable('procedure_billing_handoffs'))->toBeTrue()
        ->and(Schema::hasTable('procedure_decisions'))->toBeTrue()
        ->and(Schema::hasColumn('procedure_billing_handoffs', 'procedure_decision_id'))->toBeTrue()
        ->and(Schema::hasColumn('bills', 'procedure_billing_handoff_id'))->toBeTrue()
        ->and(Route::has('billing.procedures.index'))->toBeFalse()
        ->and(Route::has('billing.procedures.store'))->toBeFalse()
        ->and(Route::has('consultations.store'))->toBeFalse();
});
