<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Server;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * A Panelica server's TLS certificate can be checked.
 *
 * Every call to the panel skipped certificate checking, with no way to turn
 * it on, so whoever sat between the billing panel and the server could read
 * and change what passed (API keys, account passwords). Proxmox servers have
 * a "check the certificate" box; Panelica servers now have the same one. It
 * is off unless ticked, so a panel reached by IP or with its own certificate
 * keeps working.
 */
function tlsAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function tlsServerFields(array $extra = []): array
{
    return array_merge([
        'name' => 'Panel', 'hostname' => 'panel.example.test', 'ip_address' => '203.0.113.9',
        'port' => 8443, 'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'max_accounts' => 100, 'active' => 1,
    ], $extra);
}

function verifiesTls(Server $server): bool
{
    $method = new ReflectionMethod(PanelicaModule::class, 'verifiesTls');

    return (bool) $method->invoke(new PanelicaModule, $server);
}

test('a Panelica server can be saved with its certificate checked', function () {
    $this->actingAs(tlsAdmin(), 'admin')->post(route('admin.config.servers.store'), tlsServerFields([
        'settings' => ['verify_tls' => '1'],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $server = Server::where('hostname', 'panel.example.test')->firstOrFail();
    expect($server->setting('verify_tls'))->toBeTrue()
        ->and(verifiesTls($server))->toBeTrue();
});

test('it stays off unless ticked, as before', function () {
    $this->actingAs(tlsAdmin(), 'admin')->post(route('admin.config.servers.store'), tlsServerFields())
        ->assertRedirect();

    expect(verifiesTls(Server::where('hostname', 'panel.example.test')->firstOrFail()))->toBeFalse();
});

test('unticking it on the edit form turns it off again', function () {
    $admin = tlsAdmin();
    $this->actingAs($admin, 'admin')->post(route('admin.config.servers.store'), tlsServerFields(['settings' => ['verify_tls' => '1']]));
    $server = Server::where('hostname', 'panel.example.test')->firstOrFail();

    $this->actingAs($admin, 'admin')->put(route('admin.config.servers.update', $server), tlsServerFields(['password' => '', 'access_hash' => '']))
        ->assertRedirect();

    expect(verifiesTls($server->fresh()))->toBeFalse();
});

test('no call to the panel skips the check unconditionally', function () {
    $source = file_get_contents(base_path('modules/Servers/Panelica/PanelicaModule.php'));

    expect($source)->not->toContain('withoutVerifying()');
});
