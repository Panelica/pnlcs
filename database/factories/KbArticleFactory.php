<?php

namespace Database\Factories;

use App\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class KbArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => KbCategory::factory(),
            'title'       => fake()->sentence(),
            'article'     => fake()->paragraphs(3, true),
            'views'       => fake()->numberBetween(0, 500),
            'useful'      => 0,
            'votes'       => 0,
            'private'     => false,
            'sort_order'  => 0,
        ];
    }
}
