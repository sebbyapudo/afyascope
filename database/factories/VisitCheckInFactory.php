<?php

namespace Database\Factories;

use App\Models\FinancialClearance;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\VisitStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitCheckIn>
 */
class VisitCheckInFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => function (): int {
                $financialClearance = FinancialClearance::factory()->create();

                return $financialClearance->bill->visit_id;
            },
            'checked_in_by_user_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (VisitCheckIn $visitCheckIn): void {
            $visit = Visit::query()->findOrFail($visitCheckIn->visit_id);
            $visit->status = VisitStatus::CheckedIn;
            $visit->save();
        });
    }
}
