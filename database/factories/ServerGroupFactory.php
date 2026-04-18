<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServerGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' Group',
            'fill_type' => fake()->randomElement(['fill', 'round_robin']),
        ];
    }
}
