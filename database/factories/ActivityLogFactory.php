<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admin' => fake()->name(),
            'action' => fake()->randomElement(['create', 'update', 'delete', 'login', 'view']),
            'description' => fake()->sentence(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
