<?php
namespace Database\Factories;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task' => fake()->sentence(),
            'status' => fake()->randomElement(['Pending', 'In Progress', 'Completed']),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+1 month'),
            'notes' => fake()->optional(0.3)->sentence(),
            'time_spent' => fake()->numberBetween(0, 480),
        ];
    }
}
