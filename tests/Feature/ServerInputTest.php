<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Server;

/**
 * What an operator pastes into the server form.
 *
 * The address is taken exactly as typed and the URL is built from it, so
 * "https://whm.example.test:2087" - the thing anybody would copy out of their
 * browser - produced https://https://whm.example.test:2087:2087/json-api/ and
 * every call failed with nothing to explain it.
 *
 * An API token copied from WHM's page carries a newline as often as not, and
 * the header was built from it raw: the panel said Access denied and the
 * operator had a token in front of them that looked perfectly correct.
 */
function serverFormAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('an address pasted with its scheme and port is cleaned up', function () {
    $this->actingAs(serverFormAdmin(), 'admin')
        ->post(route('admin.config.servers.store'), [
            'name' => 'Pasted',
            'hostname' => 'https://whm.example.test:2087/',
            'type' => 'cpanel',
            'access_hash' => 'TOKEN',
            'active' => '1',
        ])->assertSessionHasNoErrors();

    expect(Server::where('name', 'Pasted')->firstOrFail()->hostname)->toBe('whm.example.test');
});

test('a token pasted with whitespace is stored without it', function () {
    $this->actingAs(serverFormAdmin(), 'admin')
        ->post(route('admin.config.servers.store'), [
            'name' => 'Spaced',
            'hostname' => 'whm2.example.test',
            'type' => 'cpanel',
            'access_hash' => "  TOKENWITHSPACE\n",
            'active' => '1',
        ])->assertSessionHasNoErrors();

    expect(Server::where('name', 'Spaced')->firstOrFail()->access_hash)->toBe('TOKENWITHSPACE');
});

test('an address that is only a scheme is refused rather than saved empty', function () {
    $this->actingAs(serverFormAdmin(), 'admin')
        ->post(route('admin.config.servers.store'), [
            'name' => 'Broken',
            'hostname' => 'https://',
            'type' => 'cpanel',
            'access_hash' => 'TOKEN',
            'active' => '1',
        ])->assertSessionHasErrors('hostname');

    expect(Server::where('name', 'Broken')->exists())->toBeFalse();
});

test('a port pasted into the address does not overwrite the port field', function () {
    $this->actingAs(serverFormAdmin(), 'admin')
        ->post(route('admin.config.servers.store'), [
            'name' => 'Ported',
            'hostname' => 'whm3.example.test:2087',
            'port' => 2087,
            'type' => 'cpanel',
            'access_hash' => 'TOKEN',
            'active' => '1',
        ])->assertSessionHasNoErrors();

    $server = Server::where('name', 'Ported')->firstOrFail();

    expect($server->hostname)->toBe('whm3.example.test')
        ->and((int) $server->port)->toBe(2087);
});
