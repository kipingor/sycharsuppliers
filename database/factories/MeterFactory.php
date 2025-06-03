<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Meter>
 */
class MeterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'meter_name' => $this->faker->name(),
            'meter_number' => strtoupper(Str::random(12)),
            'location' => $this->faker->address(),
            'type' => $this->faker->randomElement(['analog', 'digital']),
            'status' => $this->faker->randomElement(['active', 'inactive', 'replaced']),
            'installation_date' => $this->faker->date(),
        ];
    }
}
