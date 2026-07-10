<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class DomainFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'order_id' => null,
            'type' => 'Register',
            'domain' => fake()->unique()->domainName(),
            'registrar' => 'custom',
            'registration_period' => 1,
            'registration_date' => now(),
            'expiry_date' => now()->addYear(),
            'next_due_date' => now()->addYear(),
            'status' => 'active',
            'dns_management' => false,
            'email_forwarding' => false,
            'id_protection' => false,
            'is_premium' => false,
            'payment_method' => 'banktransfer',
            'first_payment_amount' => fake()->randomFloat(2, 8, 50),
            'recurring_amount' => fake()->randomFloat(2, 8, 50),
        ];
    }

    public function transfer(): static
    {
        return $this->state(fn () => ['type' => 'Transfer']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'Expired',
            'expiry_date' => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}
