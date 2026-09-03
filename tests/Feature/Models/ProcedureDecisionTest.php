<?php

use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\ProcedureDecisionOutcome;
use Illuminate\Database\QueryException;

it('represents a server-identified immutable no-procedure decision', function () {
    $decision = ProcedureDecision::factory()->createAuthoritativeDecisionFixture([
        'decision_number' => 'PDC-FORGED',
        'decided_at' => '2020-01-01 00:00:00',
        'visit_id' => 999_999,
        'doctor_user_id' => 999_999,
        'outcome' => ProcedureDecisionOutcome::NoProcedure,
        'clinical_rationale' => 'No procedure is clinically required.',
    ]);

    expect($decision->decision_number)->toMatch('/^PDC-\d{6,}$/')
        ->and($decision->visit->is($decision->consultation->visit))->toBeTrue()
        ->and($decision->doctor->is($decision->consultation->doctor))->toBeTrue()
        ->and($decision->outcome)->toBe(ProcedureDecisionOutcome::NoProcedure)
        ->and($decision->serviceCatalogItem)->toBeNull()
        ->and($decision->procedureBillingHandoff)->toBeNull()
        ->and($decision->decided_at->equalTo($decision->created_at))->toBeTrue();

    $decision->clinical_rationale = 'Changed';

    expect(fn () => $decision->save())->toThrow(LogicException::class)
        ->and(fn () => $decision->delete())->toThrow(LogicException::class)
        ->and($decision->fresh()->clinical_rationale)->toBe('No procedure is clinically required.');
});

it('rejects ordinary procedure-decision persistence', function () {
    $consultation = Consultation::factory()->create();
    $decision = new ProcedureDecision;
    $decision->consultation()->associate($consultation);
    $decision->visit()->associate($consultation->visit);
    $decision->doctor()->associate($consultation->doctor);
    $decision->outcome = ProcedureDecisionOutcome::NoProcedure;

    expect(fn () => $decision->save())->toThrow(
        LogicException::class,
        'Procedure decisions may only be recorded through the authoritative Doctor workflow.',
    );

    expect(ProcedureDecision::query()->count())->toBe(0);
});

it('enforces one authoritative decision per Consultation and Visit at the database boundary', function () {
    $consultation = Consultation::factory()->create();
    ProcedureDecision::factory()
        ->for($consultation)
        ->createAuthoritativeDecisionFixture();

    expect(fn () => ProcedureDecision::factory()
        ->for($consultation)
        ->createAuthoritativeDecisionFixture())->toThrow(QueryException::class);

    expect(ProcedureDecision::query()->where('visit_id', $consultation->visit_id)->count())->toBe(1);
});

it('creates a handoff only from a persisted procedure-required decision', function () {
    $consultation = Consultation::factory()->create();
    $service = ServiceCatalogItem::factory()->procedure()->create();
    $procedureRequired = ProcedureDecision::factory()
        ->for($consultation)
        ->procedureRequired($service)
        ->createAuthoritativeDecisionFixture();
    $handoff = ProcedureBillingHandoff::createFromProcedureDecision($procedureRequired);

    expect($handoff->procedure_decision_id)->toBe($procedureRequired->id)
        ->and($handoff->visit_id)->toBe($procedureRequired->visit_id)
        ->and($handoff->service_catalog_item_id)->toBe($service->id)
        ->and($handoff->decided_by_user_id)->toBe($procedureRequired->doctor_user_id);

    $noProcedure = ProcedureDecision::factory()->createAuthoritativeDecisionFixture();

    expect(fn () => ProcedureBillingHandoff::createFromProcedureDecision($noProcedure))
        ->toThrow(LogicException::class);
});
