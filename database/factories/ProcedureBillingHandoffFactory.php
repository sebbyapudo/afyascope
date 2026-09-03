<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\ProcedureDecision;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\VisitCheckIn;
use App\StaffRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProcedureBillingHandoff>
 */
class ProcedureBillingHandoffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => fn (): int => VisitCheckIn::factory()->create()->visit_id,
            'service_catalog_item_id' => ServiceCatalogItem::factory()->procedure(),
            'decided_by_user_id' => User::factory()->forRole(StaffRole::Doctor),
            'handoff_number' => 'TMP-'.Str::ulid(),
            'decided_at' => now(),
        ];
    }

    /**
     * Persist a test fixture representing the future authoritative Doctor decision.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createAuthoritativeDecisionFixture(array $attributes = []): ProcedureBillingHandoff
    {
        unset($attributes['handoff_number'], $attributes['decided_at']);

        $draft = $this->makeOne($attributes);
        $consultation = Consultation::query()
            ->where('visit_id', $draft->visit_id)
            ->first();

        if (! $consultation instanceof Consultation) {
            $consultation = Consultation::factory()
                ->for($draft->visit)
                ->for($draft->decidedBy, 'doctor')
                ->create();
        }

        $decision = ProcedureDecision::factory()
            ->for($consultation)
            ->procedureRequired($draft->serviceCatalogItem)
            ->createAuthoritativeDecisionFixture();

        return ProcedureBillingHandoff::createFromProcedureDecision($decision);
    }
}
