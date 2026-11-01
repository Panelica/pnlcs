<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Http;
use Modules\Registrars\OVH\OvhRegistrar;

function ovhSettings(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'app_key'], ['value' => 'key']);
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'app_secret'], ['value' => 'secret']);
    RegistrarSettings::updateOrCreate(['registrar' => 'ovh', 'setting' => 'consumer_key'], ['value' => 'consumer']);
}

function ovhDomain(array $attrs = []): Domain
{
    return Domain::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'shop.example.com',
        'registrar' => 'ovh',
        'status' => 'active',
        'expiry_date' => now()->addYear(),
        'next_due_date' => now()->addYear(),
    ], $attrs));
}

it('reports a working connection', function () {
    ovhSettings();
    Http::fake(['*/me' => Http::response(['nichandle' => 'ab123-ovh'], 200)]);

    $result = app(OvhRegistrar::class)->testConnection();

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain('ab123-ovh');
});

it('reports a failed connection', function () {
    ovhSettings();
    Http::fake(['*/me' => Http::response(['message' => 'Not credited'], 403)]);

    $result = app(OvhRegistrar::class)->testConnection();

    expect($result['success'])->toBeFalse();
});

it('checks availability through the order cart', function () {
    ovhSettings();
    Http::fake([
        '*/order/cart' => Http::response(['cartId' => 'cart-1'], 200),
        '*/order/cart/cart-1/domain*' => Http::response([['pricingMode' => 'create-default', 'offerId' => 'off-1']], 200),
    ]);

    $result = app(OvhRegistrar::class)->checkAvailability('shop.example.com');

    expect($result['available'])->toBeTrue();
});

it('marks a transfer-only domain as unavailable for registration', function () {
    ovhSettings();
    Http::fake([
        '*/order/cart' => Http::response(['cartId' => 'cart-1'], 200),
        '*/order/cart/cart-1/domain*' => Http::response([['pricingMode' => 'transfer-default', 'offerId' => 'off-2']], 200),
    ]);

    $result = app(OvhRegistrar::class)->checkAvailability('shop.example.com');

    expect($result['available'])->toBeFalse();
});

it('reads the expiry and status when syncing a domain', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com' => Http::response([
        'expiration' => '2027-06-01T10:00:00+02:00',
        'status' => 'ok',
        'transferLockStatus' => 'locked',
        'nameServers' => ['ns1.ovh.net', 'ns2.ovh.net'],
    ], 200)]);

    $result = app(OvhRegistrar::class)->syncDomain(ovhDomain());

    expect($result['success'])->toBeTrue()
        ->and($result['expiry_date'])->toBe('2027-06-01')
        ->and($result['status'])->toBe('active')
        ->and($result['locked'])->toBeTrue();
});

it('renews a domain at OVH', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/renew' => Http::response([], 200)]);

    $result = app(OvhRegistrar::class)->renew(ovhDomain(), 1);

    expect($result['success'])->toBeTrue();
});

it('returns the failure message when OVH refuses a renewal', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/renew' => Http::response(['message' => 'Insufficient balance'], 400)]);

    $result = app(OvhRegistrar::class)->renew(ovhDomain(), 1);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Insufficient balance');
});

it('fetches the auth info code with a GET request', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/authInfo' => Http::response('"ABC-123"', 200)]);

    $code = app(OvhRegistrar::class)->getEPPCode(ovhDomain());

    expect($code)->toBe('ABC-123');

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/domain/shop.example.com/authInfo'));
});

it('reports an unavailable auth code when OVH returns none', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/authInfo' => Http::response(null, 200)]);

    expect(app(OvhRegistrar::class)->getEPPCode(ovhDomain()))->toBe('(unavailable)');
});

it('reads the transfer lock status from the domain info', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com' => Http::response(['transferLockStatus' => 'unlocked'], 200)]);

    expect(app(OvhRegistrar::class)->getLockStatus(ovhDomain()))->toBeFalse();
});

it('toggles the transfer lock with a PUT on the domain', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com' => Http::response([], 200)]);

    $result = app(OvhRegistrar::class)->toggleLock(ovhDomain(), false);

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/domain/shop.example.com')
        && (json_decode($request->body(), true)['transferLockStatus'] ?? null) === 'unlocked');
});

it('updates nameservers through the nameServers/update route', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com/nameServers/update' => Http::response([], 200)]);

    $result = app(OvhRegistrar::class)->saveNameservers(ovhDomain(), ['ns1.ovh.net', 'ns2.ovh.net']);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return str_ends_with($request->url(), '/domain/shop.example.com/nameServers/update')
            && ($body['nameServers'] ?? null) === [['host' => 'ns1.ovh.net'], ['host' => 'ns2.ovh.net']];
    });
});

it('maps OVH name server objects back to hostnames', function () {
    ovhSettings();
    Http::fake(['*/domain/shop.example.com' => Http::response([
        'nameServers' => [
            ['id' => 1, 'nameServerType' => 'external', 'nameServer' => 'ns2.ovh.net'],
            ['id' => 2, 'nameServerType' => 'external', 'nameServer' => 'ns1.ovh.net'],
        ],
    ], 200)]);

    expect(app(OvhRegistrar::class)->getNameservers(ovhDomain()))
        ->toBe(['ns2.ovh.net', 'ns1.ovh.net']);
});
