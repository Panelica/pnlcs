<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BannedEmailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain' => fake()->email(),
            'reason' => fake()->optional(0.5)->sentence(),
        ];
    }
}
