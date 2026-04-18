<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type'               => 'product',
            'currency_id'        => Currency::factory(),
            'rel_id'             => Product::factory(),
            'monthly'            => fake()->randomFloat(2, 5, 50),
            'quarterly'          => -1,
            'semiannually'       => -1,
            'annually'           => -1,
            'biennially'         => -1,
            'triennially'        => -1,
            'monthly_setup'      => 0,
            'quarterly_setup'    => 0,
            'semiannually_setup' => 0,
            'annually_setup'     => 0,
            'biennially_setup'   => 0,
            'triennially_setup'  => 0,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['type' => 'product', 'rel_id' => $product->id]);
    }

    public function withAnnual(): static
    {
        return $this->state(fn () => [
            'monthly'  => fake()->randomFloat(2, 5, 15),
            'annually' => fake()->randomFloat(2, 50, 150),
        ]);
    }
}
