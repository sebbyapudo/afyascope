<?php

namespace Database\Factories;

use App\BillStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FinancialClearance;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialClearance>
 */
class FinancialClearanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_id' => function (): int {
                $bill = Bill::factory()->has(BillItem::factory(), 'items')->create();
                $payment = Payment::factory()->for($bill)->create();

                Receipt::factory()->for($payment)->create();

                $bill->status = BillStatus::Paid;
                $bill->save();

                return $bill->id;
            },
            'granted_by_user_id' => User::factory(),
        ];
    }
}
