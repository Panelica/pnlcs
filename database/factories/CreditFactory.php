<?php
namespace Database\Factories;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'amount' => fake()->randomFloat(2, 5, 200),
            'description' => fake()->sentence(),
            'date' => now(),
        ];
    }
}
