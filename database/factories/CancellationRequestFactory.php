<?php
namespace Database\Factories;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class CancellationRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'client_id' => Client::factory(),
            'type' => fake()->randomElement(['Immediate', 'End of Billing Period']),
            'reason' => fake()->paragraph(),
            'created_date' => now(),
        ];
    }
}
