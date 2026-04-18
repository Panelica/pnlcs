<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TicketDepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['General Support', 'Sales', 'Technical Support', 'Billing']),
            'description' => fake()->sentence(),
            'email' => fake()->safeEmail(),
            'clients_only' => false,
            'hidden' => false,
            'sort_order' => fake()->numberBetween(0, 10),
            'feedback_request' => false,
        ];
    }

    public function clientsOnly(): static
    {
        return $this->state(fn () => ['clients_only' => true]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['hidden' => true]);
    }
}
