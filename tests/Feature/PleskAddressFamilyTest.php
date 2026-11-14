<?php

use App\Models\Server;
use App\Support\ServerAddress;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Plesk\PleskModule;

/**
 * Plesk ties an API key to the caller's address. A hostname with an AAAA
 * record was called over IPv6 although the server was recorded with its IPv4,
 * so Plesk saw an address the key was not issued for and refused it.
 */
function pleskFamilyServer(string $ip): Server
{
    ServerAddress::resolveUsing(fn (): array => ['203.0.113.10']);

    return Server::factory()->create([
        'type' => 'plesk', 'hostname' => 'plesk.test', 'ip_address' => $ip, 'port' => 8443,
        'username' => 'admin', 'password' => '', 'access_hash' => 'sk-123',
    ]);
}

/** @return array{0: ArrayObject<int, array<string, mixed>>} the options of every call made */
function pleskCallOptions(): array
{
    $seen = new ArrayObject;
    Http::fake(function ($request, array $options) use ($seen) {
        $seen->append($options);

        return Http::response('<packet><service-plan><get/></service-plan></packet>', 200);
    });

    return [$seen];
}

afterEach(fn () => ServerAddress::resolveUsing(null));

it('calls a server recorded with an IPv4 address over IPv4', function () {
    [$seen] = pleskCallOptions();
    $server = pleskFamilyServer('203.0.113.10');

    (new PleskModule)->testConnection($server);
    (new PleskModule)->listPackages($server);

    expect(count($seen))->toBeGreaterThanOrEqual(2);
    foreach ($seen as $options) {
        expect($options['curl'][CURLOPT_IPRESOLVE] ?? null)->toBe(CURL_IPRESOLVE_V4);
    }
});

it('calls a server recorded with an IPv6 address over IPv6', function () {
    [$seen] = pleskCallOptions();

    (new PleskModule)->testConnection(pleskFamilyServer('2001:db8::10'));

    expect($seen[0]['curl'][CURLOPT_IPRESOLVE] ?? null)->toBe(CURL_IPRESOLVE_V6);
});

it('leaves the choice to the resolver when no IP is recorded', function () {
    [$seen] = pleskCallOptions();

    (new PleskModule)->testConnection(pleskFamilyServer(''));

    expect($seen[0]['curl'] ?? [])->not->toHaveKey(CURLOPT_IPRESOLVE);
});
