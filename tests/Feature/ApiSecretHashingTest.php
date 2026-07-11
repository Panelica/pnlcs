<?php

use App\Models\Admin;
use App\Models\ApiCredential;

/**
 * API secrets are stored as a SHA-256 digest, not plaintext. Authentication
 * hashes the presented secret and compares/looks up by hash, so a database
 * leak never exposes usable API secrets.
 */

it('stores the API secret as a sha256 hash, never plaintext', function () {
    $admin = Admin::factory()->create();
    $plain = 'my_plaintext_secret_value';

    $cred = ApiCredential::create([
        'admin_id' => $admin->id, 'identifier' => 'h_id',
        'secret' => ApiCredential::hashSecret($plain), 'active' => true,
    ]);

    expect($cred->fresh()->secret)->toBe(hash('sha256', $plain))
        ->and($cred->fresh()->secret)->not->toBe($plain)
        ->and(strlen($cred->fresh()->secret))->toBe(64);
});

it('authenticates with the plaintext secret against the stored hash', function () {
    $admin = Admin::factory()->create();
    $plain = 'plain_secret_123';
    ApiCredential::create(['admin_id' => $admin->id, 'identifier' => 'auth_id',
        'secret' => ApiCredential::hashSecret($plain), 'active' => true]);

    $this->getJson('/api/v1/getstats', ['X-API-Key' => 'auth_id', 'X-API-Secret' => $plain])->assertOk();
    $this->getJson('/api/v1/getstats', ['X-API-Key' => 'auth_id', 'X-API-Secret' => 'wrong'])->assertStatus(401);
});

it('authenticates a Bearer token by its hash', function () {
    $admin = Admin::factory()->create();
    $plain = 'bearer_secret_xyz';
    ApiCredential::create(['admin_id' => $admin->id, 'identifier' => 'b_id',
        'secret' => ApiCredential::hashSecret($plain), 'active' => true]);

    $this->getJson('/api/v1/getstats', ['Authorization' => 'Bearer ' . $plain])->assertOk();
});
