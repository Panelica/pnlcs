<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketReplyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'client_id' => Client::factory(),
            'message' => fake()->paragraphs(2, true),
            'admin' => '',
            'rating' => null,
        ];
    }

    public function fromAdmin(string $adminName = 'Admin'): static
    {
        return $this->state(fn () => [
            'client_id' => null,
            'admin' => $adminName,
        ]);
    }
}
