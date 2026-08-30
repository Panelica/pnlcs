<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['VAT', 'GST', 'Sales Tax', 'KDV']),
            'state' => '',
            'country' => fake()->countryCode(),
            'tax_rate' => fake()->randomFloat(2, 5, 25),
            'is_default' => false,
        ];
    }

    public function vat(): static
    {
        return $this->state(fn () => ['name' => 'VAT', 'tax_rate' => 20.00]);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
