<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KbCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'       => fake()->words(3, true),
            'sort_order' => fake()->numberBetween(0, 100),
            'hidden'     => false,
        ];
    }
}
