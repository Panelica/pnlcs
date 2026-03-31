<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-3 months', 'now');
        $dueDate = fake()->dateTimeBetween($date, '+30 days');

        return [
            'client_id' => Client::factory(),
            'invoice_num' => fake()->unique()->numerify('INV-######'),
            'date' => $date,
            'due_date' => $dueDate,
            'date_paid' => null,
            'subtotal' => 0,
            'credit' => 0,
            'tax' => 0,
            'tax2' => 0,
            'total' => 0,
            'tax_rate' => 0,
            'tax_rate2' => 0,
            'status' => 'Unpaid',
            'payment_method' => 'banktransfer',
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'Paid',
            'date_paid' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => 'Unpaid',
            'due_date' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'Cancelled']);
    }
}
