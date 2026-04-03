<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiCredentialFactory extends Factory
{
    protected $model = ApiCredential::class;

    public function definition(): array
    {
        return [
            'admin_id' => function () {
                $role = AdminRole::first() ?? AdminRole::factory()->fullAdmin()->create();
                return Admin::factory()->create(['role_id' => $role->id])->id;
            },
            'api_role_id' => null,
            'identifier' => 'test_' . Str::random(16),
            'secret' => Str::random(32),
            'description' => 'Test API credential',
            'allowed_ips' => null,
            'active' => true,
        ];
    }
}
