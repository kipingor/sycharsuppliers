<?php

namespace Database\Factories;

use App\Models\Meter;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MeterReading>
 */
class MeterReadingFactory extends Factory
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
            'reading_date' => $this->faker->date(),
            'reading_value' => $this->faker->randomFloat(2, 0, 10000),
            'employee_id' => Employee::factory(),
            'reading_type' => $this->faker->randomElement(['manual', 'automatic']),            
        ];
    }
}
