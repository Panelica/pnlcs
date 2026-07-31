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

    /** Plaintext secret whose SHA-256 hash is stored; tests authenticate with this. */
    public const PLAINTEXT_SECRET = 'test-secret-plaintext-0123456789abcdef0123456789abcdef01234567';

    public function definition(): array
    {
        return [
            'admin_id' => function () {
                $role = AdminRole::where('is_full_admin', true)->first()
                    ?? AdminRole::factory()->fullAdmin()->create();

                return Admin::factory()->create(['role_id' => $role->id])->id;
            },
            'api_role_id' => null,
            'identifier' => 'test_'.Str::random(16),
            'secret' => ApiCredential::hashSecret(self::PLAINTEXT_SECRET),
            'description' => 'Test API credential',
            'allowed_ips' => null,
            'active' => true,
        ];
    }
}
