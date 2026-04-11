<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        $account = Account::factory()->create();
        $resident = Resident::factory()->create([
            'account_number' => $account->account_number,
        ]);

        return [
            'account_id' => $account->id,
            'resident_id' => $resident->id,
            'meter_number' => $this->faker->unique()->numerify('MTR-#####'),
            'meter_name' => $this->faker->words(2, true),
            'location' => $this->faker->address,
            'type' => 'analog',
            'meter_type' => 'individual',
            'status' => 'active',
        ];
    }
}
