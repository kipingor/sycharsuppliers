<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'account_number' => 'ACC-' . $this->faker->unique()->numerify('#####'),
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'status' => 'active',
        ];
    }
}
