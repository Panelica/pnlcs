<?php

namespace Database\Factories;

use App\Models\AdminRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            "uuid" => Str::uuid(),
            "role_id" => AdminRole::factory(),
            "username" => fake()->unique()->userName(),
            "email" => fake()->unique()->safeEmail(),
            "password" => static::$password ??= Hash::make("password"),
            "first_name" => fake()->firstName(),
            "last_name" => fake()->lastName(),
            "language" => "en",
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ["is_disabled" => true]);
    }
}
