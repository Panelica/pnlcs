<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;

class DownloadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'location' => 'https://example.com/files/' . fake()->slug() . '.zip',
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
