<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;

class DownloadCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->optional(0.5)->sentence(),
        ];
    }
}
