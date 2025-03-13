<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'idnumber' => $this->faker->unique()->numerify('########'),
            'position' => $this->faker->jobTitle(),
            'salary' => $this->faker->randomFloat(2, 30000, 100000),
            'hire_date' => $this->faker->date(),
            'status' => $this->faker->randomElement(['active', 'inactive', 'terminated']),
        ];
    }
}
