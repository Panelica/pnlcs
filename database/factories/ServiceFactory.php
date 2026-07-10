<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'server_id' => null,
            'domain' => fake()->domainName(),
            'payment_method' => 'banktransfer',
            'qty' => 1,
            'first_payment_amount' => fake()->randomFloat(2, 5, 200),
            'amount' => fake()->randomFloat(2, 5, 200),
            'billing_cycle' => fake()->randomElement(['Monthly', 'Quarterly', 'Semi-Annually', 'Annually']),
            'next_due_date' => fake()->dateTimeBetween('now', '+1 year'),
            'registration_date' => now(),
            'status' => 'active',
            'username' => fake()->optional(0.5)->userName(),
            'password' => null,
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => 'suspended',
            'suspension_date' => now(),
            'suspension_reason' => fake()->sentence(),
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'status' => 'terminated',
            'termination_date' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}
