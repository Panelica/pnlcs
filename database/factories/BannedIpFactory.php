<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BannedIpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ip'     => fake()->ipv4(),
            'reason' => fake()->optional(0.7)->sentence(),
        ];
    }
}
