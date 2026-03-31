<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkIssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(['Maintenance', 'Outage', 'Degradation']),
            'status' => fake()->randomElement(['Reported', 'Investigating', 'Identified', 'Monitoring', 'Resolved']),
            'affected' => fake()->words(3, true),
            'start_date' => now(),
            'end_date' => null,
        ];
    }
    public function resolved(): static { return $this->state(fn () => ['status' => 'Resolved', 'end_date' => now()]); }
}
