<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Meter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Billing>
 */
class BillingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $meter = Meter::factory()->create();

        return [
            'meter_id' => $meter->id,
            'account_id' => $meter->account_id,
            'billing_period' => now()->format('Y-m'),
            'total_amount' => $this->faker->randomFloat(2, 10, 500),
            'amount_due' => $this->faker->randomFloat(2, 10, 500),
            'status' => $this->faker->randomElement(['paid', 'pending', 'overdue', 'partially_paid', 'voided']),
            'issued_at' => now(),
            'due_date' => now()->addMonth(),
        ];
    }
}
