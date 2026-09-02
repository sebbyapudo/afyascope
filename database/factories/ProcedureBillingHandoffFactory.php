<?php

namespace Database\Factories;

use App\Models\ProcedureBillingHandoff;
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

        $handoff = $this->makeOne($attributes);
        $handoff->saveQuietly();
        $handoff->handoff_number = sprintf('PBH-%06d', $handoff->id);
        $handoff->saveQuietly();

        return $handoff;
    }
}
