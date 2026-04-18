<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'currency_id' => null,
            'gateway' => fake()->randomElement(['banktransfer', 'paypal', 'stripe']),
            'date' => fake()->dateTimeBetween('-6 months', 'now'),
            'description' => fake()->sentence(),
            'amount_in' => fake()->randomFloat(2, 10, 500),
            'fees' => fake()->randomFloat(2, 0, 10),
            'amount_out' => 0,
            'rate' => 1,
            'transaction_id' => fake()->optional(0.7)->uuid(),
            'invoice_id' => null,
        ];
    }

    public function refund(): static
    {
        return $this->state(fn () => [
            'amount_in' => 0,
            'amount_out' => fake()->randomFloat(2, 10, 200),
            'description' => 'Refund - ' . fake()->sentence(),
        ]);
    }
}
