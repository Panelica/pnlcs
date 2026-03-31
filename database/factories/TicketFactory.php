<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\TicketDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tid' => fake()->unique()->numerify('######'),
            'department_id' => TicketDepartment::factory(),
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'title' => fake()->sentence(),
            'message' => fake()->paragraphs(2, true),
            'status' => 'Open',
            'priority' => fake()->randomElement(['Low', 'Medium', 'High']),
            'last_reply' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'Closed']);
    }

    public function answered(): static
    {
        return $this->state(fn () => ['status' => 'Answered']);
    }

    public function customerReply(): static
    {
        return $this->state(fn () => ['status' => 'Customer-Reply']);
    }

    public function highPriority(): static
    {
        return $this->state(fn () => ['priority' => 'High']);
    }
}
