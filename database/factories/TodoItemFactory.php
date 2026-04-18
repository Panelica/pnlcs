<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TodoItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->optional(0.5)->paragraph(),
            'status' => fake()->randomElement(['New', 'In Progress', 'Completed']),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+30 days'),
            'admin' => fake()->name(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'Completed']);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => 'In Progress']);
    }
}
