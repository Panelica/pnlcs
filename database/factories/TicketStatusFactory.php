<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TicketStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Open', 'Answered', 'Customer-Reply', 'Closed', 'On Hold']),
            'color' => fake()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 10),
            'show_active' => true,
            'show_awaiting' => false,
            'auto_close' => false,
        ];
    }
}
