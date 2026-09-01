<?php

namespace Database\Factories;

use App\BillType;
use App\Models\ServiceCatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCatalogItem>
 */
class ServiceCatalogItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Consultation',
            'category' => BillType::Consultation,
            'is_active' => true,
            'unit_price_minor' => fake()->numberBetween(10_000, 500_000),
        ];
    }

    public function procedure(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Procedure',
            'category' => BillType::Procedure,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
