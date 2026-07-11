<?php

use App\Models\Admin;
use App\Models\ApiCredential;

/**
 * Guards the API authentication middleware. A critical bypass let any request
 * that supplied a valid identifier but NO secret through — the secret was only
 * checked when it happened to be present. The identifier is not a secret, so
 * this exposed the entire admin API. The secret is now mandatory and compared
 * in constant time.
 */

function apiCred(string $identifier, string $secret): ApiCredential
{
    $admin = Admin::factory()->create();
    return ApiCredential::create([
        'admin_id'   => $admin->id,
        'identifier' => $identifier,
        'secret'     => ApiCredential::hashSecret($secret),
        'active'     => true,
    ]);
}

it('rejects an API request with a valid identifier but no secret (the bypass)', function () {
    apiCred('id_nosecret', 'the_secret');

    $this->getJson('/api/v1/getstats', ['X-API-Key' => 'id_nosecret'])
        ->assertStatus(401);
});

it('rejects an API request with a wrong secret', function () {
    apiCred('id_wrong', 'right_secret');

    $this->getJson('/api/v1/getstats', ['X-API-Key' => 'id_wrong', 'X-API-Secret' => 'nope'])
        ->assertStatus(401);
});

it('accepts an API request with the correct identifier and secret', function () {
    apiCred('id_ok', 'correct_secret');

    $this->getJson('/api/v1/getstats', ['X-API-Key' => 'id_ok', 'X-API-Secret' => 'correct_secret'])
        ->assertOk();
});

it('rejects an API request with no credentials at all', function () {
    $this->getJson('/api/v1/getstats')->assertStatus(401);
});

it('still rejects an inactive credential even with the right secret', function () {
    $admin = Admin::factory()->create();
    ApiCredential::create([
        'admin_id' => $admin->id, 'identifier' => 'id_inactive',
        'secret' => ApiCredential::hashSecret('sec'), 'active' => false,
    ]);

    $this->getJson('/api/v1/getstats', ['X-API-Key' => 'id_inactive', 'X-API-Secret' => 'sec'])
        ->assertStatus(401);
});
