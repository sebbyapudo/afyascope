<?php

namespace Database\Factories;

use App\BillType;
use App\Models\Bill;
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

    public function procedure(): static
    {
        return $this->state(fn (): array => [
            'type' => BillType::Procedure,
        ]);
    }
}
