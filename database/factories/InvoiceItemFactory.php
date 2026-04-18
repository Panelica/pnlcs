<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'client_id' => Client::factory(),
            'type' => fake()->randomElement(['Hosting', 'Domain', 'Addon', 'Other']),
            'rel_id' => 0,
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 5.00, 500.00),
            'taxed' => fake()->boolean(70),
        ];
    }
}
