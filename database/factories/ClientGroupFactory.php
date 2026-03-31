<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClientGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            "name" => fake()->words(2, true),
            "color" => fake()->hexColor(),
            "discount_percent" => fake()->randomFloat(2, 0, 25),
        ];
    }
}
