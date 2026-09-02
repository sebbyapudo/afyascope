<?php

namespace Database\Factories;

use App\BillType;
use App\Models\Bill;
use App\Models\ProcedureBillingHandoff;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'type' => BillType::Consultation,
        ];
    }

    public function procedure(?ProcedureBillingHandoff $handoff = null): static
    {
        return $this->state(function () use ($handoff): array {
            $procedureBillingHandoff = $handoff
                ?? ProcedureBillingHandoff::factory()->createAuthoritativeDecisionFixture();

            return [
                'visit_id' => $procedureBillingHandoff->visit_id,
                'procedure_billing_handoff_id' => $procedureBillingHandoff->id,
                'type' => BillType::Procedure,
            ];
        });
    }
}
