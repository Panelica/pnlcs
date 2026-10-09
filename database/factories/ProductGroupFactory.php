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
            // Two random words collide often enough across a full test run to
            // break the unique index now and then; the suffix keeps them apart.
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'headline' => fake()->optional(0.5)->sentence(),
            'tagline' => fake()->optional(0.5)->words(5, true),
            'order_form_template' => 'standard_cart',
            'hidden' => false,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
