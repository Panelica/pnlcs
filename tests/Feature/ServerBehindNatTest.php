<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Server;
use App\Models\Service;
use App\Support\ServerAddress;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * #334. A panel on a private address, reached from outside through a router,
 * is recorded with its private IP (which PNLCS calls) and its public hostname
 * (which customers' links use). Saving that said "one of the two is pointing
 * at the wrong machine", and every call wrote the same warning to the log.
 */

beforeEach(function () {
    ServerAddress::resolveUsing(fn (string $host) => [
        'panel.example.test' => ['203.0.113.10'],
        'inside.example.test' => ['10.0.0.9'],
    ][$host] ?? []);
});

afterEach(function () {
    ServerAddress::resolveUsing(null);
});

function natAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function saveServer(string $hostname, string $ip)
{
    return test()->actingAs(natAdmin(), 'admin')->post(route('admin.config.servers.store'), [
        'name' => 'Panel', 'hostname' => $hostname, 'ip_address' => $ip, 'port' => 8443,
        'type' => 'custom', 'username' => 'admin', 'password' => 'secret', 'max_accounts' => 100, 'active' => 1,
    ]);
}

test('a private address beside a public hostname is told apart from a wrong one', function () {
    expect(ServerAddress::behindNat('192.168.245.20', ['203.0.113.10']))->toBeTrue()
        ->and(ServerAddress::behindNat('10.0.0.5', ['203.0.113.10', '203.0.113.11']))->toBeTrue()
        // Both public: one of them is wrong.
        ->and(ServerAddress::behindNat('198.51.100.7', ['203.0.113.10']))->toBeFalse()
        // The hostname leads inside as well, to another machine.
        ->and(ServerAddress::behindNat('10.0.0.5', ['10.0.0.9']))->toBeFalse()
        ->and(ServerAddress::behindNat('10.0.0.5', ['10.0.0.9', '203.0.113.10']))->toBeFalse()
        ->and(ServerAddress::behindNat('10.0.0.5', []))->toBeFalse()
        ->and(ServerAddress::behindNat('not-an-ip', ['203.0.113.10']))->toBeFalse();
});

test('saving a server behind NAT says how it will be used, not that it is wrong', function () {
    saveServer('panel.example.test', '192.168.245.20')
        ->assertRedirect()
        ->assertSessionHas('info', fn ($message) => str_contains($message, '192.168.245.20') && str_contains($message, 'panel.example.test'))
        ->assertSessionMissing('warning');
});

test('a hostname that leads to another public address is still a warning', function () {
    saveServer('panel.example.test', '198.51.100.7')
        ->assertSessionHas('warning', __('admin.servers.hostname_mismatch', [
            'host' => 'panel.example.test', 'resolved' => '203.0.113.10', 'ip' => '198.51.100.7',
        ]));
});

test('a hostname that leads to another private machine is still a warning', function () {
    saveServer('inside.example.test', '10.0.0.5')->assertSessionHas('warning');
});

test('a hostname that does not resolve is still a warning', function () {
    saveServer('nowhere.example.test', '10.0.0.5')
        ->assertSessionHas('warning', __('admin.servers.hostname_unresolved', ['host' => 'nowhere.example.test']));
});

test('a server behind NAT is called on its IP without a warning in the log', function () {
    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'panel.example.test', 'ip_address' => '192.168.245.20', 'port' => 8443]);
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['status' => 'online']])]);
    Log::spy();

    expect((new PanelicaModule)->testConnection($server))->toBeTrue();

    Http::assertSent(fn (Request $request) => parse_url($request->url(), PHP_URL_HOST) === '192.168.245.20');
    Log::shouldNotHaveReceived('warning');
});

test('a hostname that leads elsewhere is called on the IP and logged, as before', function () {
    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'panel.example.test', 'ip_address' => '198.51.100.7', 'port' => 8443]);
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['status' => 'online']])]);
    Log::spy();

    (new PanelicaModule)->testConnection($server);

    Http::assertSent(fn (Request $request) => parse_url($request->url(), PHP_URL_HOST) === '198.51.100.7');
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'resolves elsewhere'));
});

test('customers are sent to the hostname of a server behind NAT', function () {
    $server = Server::factory()->create(['type' => 'panelica', 'hostname' => 'panel.example.test', 'ip_address' => '192.168.245.20', 'port' => 8443]);
    $service = Service::factory()->create(['server_id' => $server->id]);

    expect((new PanelicaModule)->webmailUrl($service))->toBe('https://panel.example.test:8443/email/webmail');
});
