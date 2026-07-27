<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => $this->freeCode(),
            'prefix' => fake()->randomElement(['$', '€', '£', '₺']),
            'suffix' => '',
            'format' => 1,
            'rate' => fake()->randomFloat(5, 0.5, 35.0),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'code' => $this->freeCode('USD'),
            'prefix' => '$',
            'suffix' => '',
            'rate' => 1.00000,
            'is_default' => true,
        ]);
    }

    /**
     * Faker's unique() only tracks codes generated in this process, so on a
     * seeded database (which is what every real install looks like) it happily
     * returns a code that already exists and the insert dies on the unique
     * index. Ask the database instead.
     */
    private function freeCode(?string $preferred = null): string
    {
        $taken = Currency::pluck('code')->map(fn ($c) => strtoupper((string) $c))->all();

        if ($preferred && ! in_array(strtoupper($preferred), $taken, true)) {
            return strtoupper($preferred);
        }

        for ($i = 0; $i < 50; $i++) {
            $code = strtoupper(fake()->unique()->currencyCode());
            if (! in_array($code, $taken, true)) {
                return $code;
            }
        }

        // Fall back to a synthetic three-letter code.
        do {
            $code = strtoupper(fake()->lexify('???'));
        } while (in_array($code, $taken, true));

        return $code;
    }
}
