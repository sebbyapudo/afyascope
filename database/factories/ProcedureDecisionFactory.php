<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\ProcedureDecisionOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProcedureDecision>
 */
class ProcedureDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultation_id' => Consultation::factory(),
            'service_catalog_item_id' => null,
            'outcome' => ProcedureDecisionOutcome::NoProcedure,
            'clinical_rationale' => null,
        ];
    }

    public function procedureRequired(?ServiceCatalogItem $serviceCatalogItem = null): static
    {
        return $this->state(fn (): array => [
            'service_catalog_item_id' => $serviceCatalogItem?->getKey()
                ?? ServiceCatalogItem::factory()->procedure(),
            'outcome' => ProcedureDecisionOutcome::ProcedureRequired,
        ]);
    }

    /**
     * Persist a test fixture representing the authoritative Doctor decision action.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createAuthoritativeDecisionFixture(array $attributes = []): ProcedureDecision
    {
        unset(
            $attributes['visit_id'],
            $attributes['doctor_user_id'],
            $attributes['decision_number'],
            $attributes['decided_at'],
            $attributes['procedure_billing_handoff_id'],
        );

        $decision = $this->makeOne($attributes);
        $consultation = Consultation::query()->findOrFail($decision->consultation_id);
        $decision->visit_id = $consultation->visit_id;
        $decision->doctor_user_id = $consultation->doctor_user_id;
        $decision->decision_number = 'TMP-'.Str::ulid();
        $decision->decided_at = now();
        $decision->saveQuietly();
        $decision->decision_number = sprintf('PDC-%06d', $decision->id);
        $decision->saveQuietly();

        return $decision;
    }
}
