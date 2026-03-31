<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['general', 'product', 'domain', 'invoice', 'support', 'affiliate']),
            'name' => ucwords(fake()->words(3, true)),
            'subject' => fake()->sentence(),
            'message' => '<p>' . fake()->paragraphs(3, true) . '</p>',
            'from_name' => fake()->optional(0.3)->company(),
            'from_email' => fake()->optional(0.3)->safeEmail(),
            'disabled' => false,
            'custom' => false,
            'language' => '',
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['disabled' => true]);
    }

    public function custom(): static
    {
        return $this->state(fn () => ['custom' => true]);
    }
}
