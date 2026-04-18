<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_num' => fake()->unique()->numerify('########'),
            'client_id' => Client::factory(),
            'date' => now(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'payment_method' => 'banktransfer',
            'invoice_id' => null,
            'status' => 'Active',
            'ip_address' => fake()->ipv4(),
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'Pending']);
    }

    public function fraud(): static
    {
        return $this->state(fn () => ['status' => 'Fraud']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'Cancelled']);
    }
}
