<?php

use App\Http\Controllers\Admin\ConfigController;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Server;
use Illuminate\Support\Facades\Http;

/**
 * "Test connection" opened a socket to the record's host and port before
 * asking the module. A record without a port fell back to 22 for HestiaCP,
 * aaPanel and CyberPanel, and Vultr was tested at the record's host although
 * the module only ever talks to api.vultr.com.
 */
it('uses each panel\'s own port when the record has none', function () {
    expect(ConfigController::defaultServerPort('hestiacp'))->toBe(8083)
        ->and(ConfigController::defaultServerPort('aapanel'))->toBe(8888)
        ->and(ConfigController::defaultServerPort('cyberpanel'))->toBe(8090)
        ->and(ConfigController::defaultServerPort('panelica'))->toBe(8443)
        ->and(ConfigController::defaultServerPort('cpanel'))->toBe(2087)
        ->and(ConfigController::defaultServerPort('custom'))->toBe(22);
});

function vultrRecord(): Server
{
    return Server::factory()->create([
        'type' => 'vultr', 'name' => 'Vultr', 'hostname' => 'unused.invalid', 'ip_address' => '',
        'port' => 443, 'username' => '', 'password' => '', 'access_hash' => 'VULTRKEY', 'active' => true,
    ]);
}

function portsAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

it('tests Vultr against its API, not the record\'s host', function () {
    Http::fake(['api.vultr.com/*' => Http::response(['account' => ['name' => 'x']], 200)]);
    $server = vultrRecord();

    $this->actingAs(portsAdmin(), 'admin')
        ->post(route('admin.config.servers.test', $server))
        ->assertSessionHas('success');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.vultr.com/'));
});

it('reports a Vultr key the API refuses', function () {
    Http::fake(['api.vultr.com/*' => Http::response(['error' => 'Invalid API token'], 401)]);

    $this->actingAs(portsAdmin(), 'admin')
        ->post(route('admin.config.servers.test', vultrRecord()))
        ->assertSessionHas('error');
});
