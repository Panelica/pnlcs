<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        $currencies = [
            ['code' => 'USD', 'prefix' => '$', 'suffix' => ' USD'],
            ['code' => 'EUR', 'prefix' => '€', 'suffix' => ' EUR'],
            ['code' => 'GBP', 'prefix' => '£', 'suffix' => ' GBP'],
            ['code' => 'TRY', 'prefix' => '₺', 'suffix' => ' TRY'],
        ];
        $currency = fake()->randomElement($currencies);

        return [
            'code' => fake()->unique()->currencyCode(),
            'prefix' => $currency['prefix'],
            'suffix' => '',
            'format' => 1,
            'rate' => fake()->randomFloat(5, 0.5, 35.0),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'code' => 'USD',
            'prefix' => '$',
            'suffix' => '',
            'rate' => 1.00000,
            'is_default' => true,
        ]);
    }
}
