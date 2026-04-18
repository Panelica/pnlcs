<?php

namespace Database\Factories;

use App\Models\ProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true) . ' Hosting';

        return [
            'type' => 'hostingaccount',
            'group_id' => ProductGroup::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'hidden' => false,
            'show_domain_options' => true,
            'is_featured' => fake()->boolean(20),
            'retired' => false,
            'pay_type' => 'recurring',
            'auto_setup' => 'order',
            'server_type' => 'custom',
            'stock_control' => false,
            'stock_qty' => 0,
            'tax' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['hidden' => true]);
    }

    public function retired(): static
    {
        return $this->state(fn () => ['retired' => true]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function free(): static
    {
        return $this->state(fn () => ['pay_type' => 'free']);
    }
}
