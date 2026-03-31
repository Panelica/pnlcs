<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            "client_id" => Client::factory(),
            "first_name" => fake()->firstName(),
            "last_name" => fake()->lastName(),
            "email" => fake()->safeEmail(),
            "phone_number" => fake()->phoneNumber(),
            "country" => fake()->countryCode(),
        ];
    }
}
