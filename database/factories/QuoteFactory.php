<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id'      => Client::factory(),
            'subject'        => fake()->sentence(4),
            'date'           => now()->toDateString(),
            'valid_until'    => now()->addDays(30)->toDateString(),
            'status'         => 'Draft',
            'subtotal'       => 0,
            'tax'            => 0,
            'total'          => 0,
            'notes'          => fake()->optional(0.4)->sentence(),
            'customer_notes' => null,
            'proposal'       => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'Sent']);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => 'Accepted']);
    }

    public function declined(): static
    {
        return $this->state(fn () => ['status' => 'Declined']);
    }
}
