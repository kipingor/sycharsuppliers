<?php

namespace Database\Factories;

use App\Models\Billing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillingMeterReadingDetail>
 */
class BillingMeterReadingDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_id' => Billing::factory(),
            'previous_reading_value' => $this->faker->randomFloat(0, 1000, 2),
            'current_reading_value' => $this->faker->randomFloat(0, 1000, 2),
            'units_used' => $this->faker->randomFloat(0, 100, 2),
        ];
    }
}
