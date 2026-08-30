<?php

namespace Database\Factories;

use App\Models\Patient;
use App\PatientSex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->optional()->dateTimeBetween('-90 years', '-1 day'),
            'sex' => fake()->optional()->randomElement(PatientSex::cases()),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
        ];
    }

    public function withoutOptionalDemographics(): static
    {
        return $this->state(fn (): array => [
            'middle_name' => null,
            'date_of_birth' => null,
            'sex' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
        ]);
    }
}
