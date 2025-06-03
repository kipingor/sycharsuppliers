<?php

namespace Database\Factories;

use App\Models\Meter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
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
            'payment_date' => $this->faker->date(),
            'amount' => $this->faker->randomFloat(2, 300, 10000),
            'method' => $this->faker->randomElement(['M-Pesa', 'Bank Transfer', 'Cash']),
            'transaction_id' => strtoupper(Str::random(12)),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed']),
        ];
    }
}
