<?php

namespace Database\Factories;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quote_id'    => Quote::factory(),
            'description' => fake()->sentence(5),
            'quantity'    => fake()->numberBetween(1, 10),
            'unit_price'  => fake()->randomFloat(2, 10, 500),
            'discount'    => 0,
            'taxable'     => true,
            'sort_order'  => 0,
        ];
    }
}
