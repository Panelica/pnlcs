<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AdminRoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            "name" => fake()->jobTitle(),
            "description" => fake()->sentence(),
            "is_full_admin" => false,
            "permissions" => ["list_clients", "view_clients"],
        ];
    }

    public function fullAdmin(): static
    {
        return $this->state(fn () => [
            "name" => "Full Administrator",
            "is_full_admin" => true,
        ]);
    }
}
