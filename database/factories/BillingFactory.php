<?php

namespace Database\Factories;

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
        return [
            'meter_id' => Meter::factory(),
            'amount_due' => $this->faker->randomFloat(2, 10, 500),
            'status' => $this->faker->randomElement(['paid', 'pending', 'unpaid', 'overdue', 'partially paid', 'void']),
        ];
    }
}
