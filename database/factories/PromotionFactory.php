<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO-????##')),
            'type' => 'Percentage',
            'recurring' => false,
            'value' => fake()->randomFloat(2, 5, 50),
            'cycles' => 0,
            'applies_to' => null,
            'requires' => null,
            'start_date' => now(),
            'expiration_date' => now()->addMonths(3),
            'max_uses' => fake()->randomElement([0, 10, 50, 100]),
            'uses' => 0,
            'lifetime_promo' => false,
            'apply_once' => false,
            'new_signups_only' => false,
            'existing_client' => false,
            'upgrades' => false,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function percentage(float $value = 10): static
    {
        return $this->state(fn () => ['type' => 'Percentage', 'value' => $value]);
    }

    public function fixed(float $value = 5): static
    {
        return $this->state(fn () => ['type' => 'Fixed Amount', 'value' => $value]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expiration_date' => now()->subDay()]);
    }

    public function recurring(): static
    {
        return $this->state(fn () => ['recurring' => true]);
    }
}
