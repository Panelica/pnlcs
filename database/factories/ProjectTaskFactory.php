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
            // The columns project_tasks actually has: it never had a status or
            // a time_spent, so this factory could not create a single row.
            'completed' => fake()->boolean(30),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+1 month'),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
