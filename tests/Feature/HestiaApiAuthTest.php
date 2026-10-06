<?php

use App\Models\Server;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Servers\HestiaCP\HestiaCPModule;

/**
 * How the module signs in to HestiaCP, and what it reads of its plans.
 *
 * HestiaCP answers /api with a redirect to /api/, and a redirected POST arrives
 * without its body: every call failed with "data received is null or invalid".
 * The Access Hash field was sent as the admin password, so an access key (the
 * ID:Secret pair v-add-access-key prints, which HestiaCP reads from `hash`)
 * never worked either.
 */
function hestiaAuthServer(array $attributes = []): Server
{
    return Server::factory()->create(array_merge([
        'type' => 'hestiacp',
        'hostname' => 'hestia.test',
        'ip_address' => '',
        'port' => 8083,
        'username' => 'admin',
        'password' => 'admin-secret',
        'access_hash' => '',
    ], $attributes));
}

function hestiaPlans(): string
{
    return json_encode([
        'pro' => ['WEB_DOMAINS' => '10'],
        'default' => ['WEB_DOMAINS' => '1'],
        'business' => ['WEB_DOMAINS' => 'unlimited'],
    ]);
}

test('an access key is sent as the hash, without the admin password', function () {
    Http::fake(['*' => Http::response(hestiaPlans(), 200)]);

    (new HestiaCPModule)->listPackages(hestiaAuthServer(['access_hash' => ' KEYID:SECRET ']));

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://hestia.test:8083/api/'
            && $request['hash'] === 'KEYID:SECRET'
            && ! isset($request['password'])
            && ! isset($request['user'])
            && $request['cmd'] === 'v-list-user-packages';
    });
});

test('without an access key the admin user and password are sent', function () {
    Http::fake(['*' => Http::response(hestiaPlans(), 200)]);

    (new HestiaCPModule)->listPackages(hestiaAuthServer());

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://hestia.test:8083/api/'
            && $request['user'] === 'admin'
            && $request['password'] === 'admin-secret'
            && ! isset($request['hash']);
    });
});

test('the plans are the server\'s own, by name', function () {
    Http::fake(['*' => Http::response(hestiaPlans(), 200)]);

    expect((new HestiaCPModule)->listPackages(hestiaAuthServer()))->toBe([
        ['id' => 'business', 'name' => 'business'],
        ['id' => 'default', 'name' => 'default'],
        ['id' => 'pro', 'name' => 'pro'],
    ]);
});

test('a refused or unreadable answer offers no plans', function (int $status, string $body) {
    Http::fake(['*' => Http::response($body, $status)]);

    expect((new HestiaCPModule)->listPackages(hestiaAuthServer()))->toBe([]);
})->with([
    'wrong credentials' => [200, 'Error: authentication failed'],
    'server error' => [500, ''],
]);
