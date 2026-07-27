<?php

use App\Models\Admin;
use App\Models\ApiCredential;

/**
 * /api/health is reachable without credentials so uptime monitors can use it.
 * It used to answer with the exact Laravel and PHP versions, the server's disk
 * capacity and usage, memory limits — and, when the database was down, the raw
 * connection exception, which can carry host and credential detail. Anonymous
 * callers now get liveness only.
 */
test('the public health probe reports liveness without disclosing the stack', function () {
    $response = $this->getJson('/api/health')->assertOk();

    $health = $response->json('health');

    expect($health['status'])->toBe('ok')
        ->and($health)->toHaveKey('timestamp')
        ->and($health)->not->toHaveKey('laravel')
        ->and($health)->not->toHaveKey('php')
        ->and($health)->not->toHaveKey('disk')
        ->and($health)->not->toHaveKey('memory')
        ->and($health)->not->toHaveKey('version');
});

test('the authenticated health endpoint still returns the full picture', function () {
    $secret = 'test-secret-value';
    $credential = ApiCredential::create([
        'admin_id' => Admin::factory()->create()->id,
        'identifier' => 'test-identifier',
        'secret' => ApiCredential::hashSecret($secret),
        'description' => 'health test',
        'active' => true,
    ]);

    $health = $this->getJson('/api/v1/gethealthstatus?api_key='.$credential->identifier.'&api_secret='.$secret)
        ->assertOk()
        ->json('health');

    expect($health)->toHaveKeys(['status', 'laravel', 'php', 'database', 'disk', 'memory']);
});
