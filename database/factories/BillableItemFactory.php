<?php
namespace Database\Factories;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillableItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'due_date' => fake()->dateTimeBetween('now', '+30 days'),
            'invoiced' => false,
        ];
    }
}
