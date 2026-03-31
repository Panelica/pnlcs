<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' Server',
            'hostname' => fake()->domainName(),
            'ip_address' => fake()->ipv4(),
            'max_accounts' => fake()->randomElement([50, 100, 200, 500]),
            'type' => 'custom',
            'username' => fake()->userName(),
            'password' => fake()->password(12),
            'port' => 2222,
            'active' => true,
            'disabled' => false,
            'nameserver1' => 'ns1.' . fake()->domainName(),
            'nameserver2' => 'ns2.' . fake()->domainName(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['disabled' => true]);
    }
}
