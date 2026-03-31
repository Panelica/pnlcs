<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            "first_name" => fake()->firstName(),
            "last_name" => fake()->lastName(),
            "email" => fake()->unique()->safeEmail(),
            "email_verified_at" => now(),
            "password" => static::$password ??= Hash::make("password"),
            "language" => "en",
            "is_active" => true,
            "remember_token" => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            "email_verified_at" => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            "is_active" => false,
        ]);
    }
}
