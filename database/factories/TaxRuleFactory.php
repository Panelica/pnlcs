<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'level' => 1,
            'name' => fake()->randomElement(['VAT', 'GST', 'Sales Tax', 'KDV']),
            'state' => '',
            'country' => fake()->countryCode(),
            'tax_rate' => fake()->randomFloat(2, 5, 25),
        ];
    }

    public function vat(): static
    {
        return $this->state(fn () => ['name' => 'VAT', 'tax_rate' => 20.00]);
    }

    public function level2(): static
    {
        return $this->state(fn () => ['level' => 2]);
    }
}
