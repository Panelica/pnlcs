<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductGroupFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'headline' => fake()->optional(0.5)->sentence(),
            'tagline' => fake()->optional(0.5)->words(5, true),
            'order_form_template' => 'standard_cart',
            'hidden' => false,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
