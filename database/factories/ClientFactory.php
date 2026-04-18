<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            "uuid" => Str::uuid(),
            "first_name" => fake()->firstName(),
            "last_name" => fake()->lastName(),
            "company_name" => fake()->optional(0.5)->company(),
            "email" => fake()->unique()->safeEmail(),
            "address1" => fake()->streetAddress(),
            "city" => fake()->city(),
            "state" => fake()->state(),
            "postcode" => fake()->postcode(),
            "country" => fake()->countryCode(),
            "phone_number" => fake()->phoneNumber(),
            "status" => ClientStatus::Active,
            "credit" => 0,
            "language" => "en",
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ["status" => ClientStatus::Inactive]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ["status" => ClientStatus::Closed]);
    }
}
