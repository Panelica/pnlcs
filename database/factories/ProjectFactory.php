<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id'   => Client::factory(),
            'admin_id'    => null,
            'title'       => fake()->sentence(3),
            'description' => fake()->optional(0.6)->paragraph(),
            'status'      => fake()->randomElement(['pending', 'in_progress', 'completed', 'cancelled']),
            'due_date'    => fake()->optional(0.7)->dateTimeBetween('now', '+3 months'),
            'start_date'  => fake()->optional(0.5)->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
