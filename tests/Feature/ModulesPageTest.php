<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\GatewaySettings;
use App\Models\Product;
use App\Models\RegistrarSettings;
use App\Models\Server;
use App\Services\Module\ModuleRegistry;

/*
 * The Modules screen: every installed module with its on/off switch.
 *
 * Gateways, registrars and addons already had switches; the screen must flip
 * THOSE, so the checkout and domain search see the same thing it shows.
 * Server and SSL modules get their first switch here, and it may not be
 * turned off while anything uses the module.
 */

function modulesAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function toggleModule($test, Admin $admin, string $type, string $key, bool $on)
{
    return $test->actingAs($admin, 'admin')->post(
        route('admin.config.modules.toggle', [$type, $key]),
        ['active' => $on ? '1' : '0']
    );
}

test('the page lists every module type with the built-in modules', function () {
    $this->actingAs(modulesAdmin(), 'admin')
        ->get(route('admin.config.modules'))
        ->assertOk()
        ->assertSee('data-module="server/panelica"', false)
        ->assertSee('data-module="gateway/stripe"', false)
        ->assertSee('data-module="gateway/iyzico"', false)
        ->assertSee('data-module="registrar/namecheap"', false)
        ->assertSee('data-module="ssl/gogetssl"', false)
        ->assertSee('data-module="addon/', false);
});

test('switching a gateway writes the same setting the checkout reads', function () {
    $admin = modulesAdmin();

    toggleModule($this, $admin, 'gateway', 'banktransfer', true)->assertRedirect();
    expect(GatewaySettings::where('gateway', 'banktransfer')->where('setting', 'active')->first()->value)->toBe('1');

    toggleModule($this, $admin, 'gateway', 'banktransfer', false)->assertRedirect();
    expect(GatewaySettings::where('gateway', 'banktransfer')->where('setting', 'active')->first()->value)->toBe('0')
        ->and(app(ModuleRegistry::class)->usableGateways())->not->toContain('banktransfer');
});

test('switching a registrar writes the visibility the domain search reads', function () {
    toggleModule($this, modulesAdmin(), 'registrar', 'namecheap', true)->assertRedirect();

    expect(RegistrarSettings::where('registrar', 'namecheap')->where('setting', 'visible')->first()->value)->toBe('1');
});

test('an unused server module switched off disappears from the forms that choose one', function () {
    $admin = modulesAdmin();
    Server::whereRaw('LOWER(type) = ?', ['vultr'])->delete();
    Product::whereRaw('LOWER(server_type) = ?', ['vultr'])->update(['server_type' => null]);

    toggleModule($this, $admin, 'server', 'vultr', false)->assertSessionHas('success');

    expect(app(ModuleRegistry::class)->serverModuleNames())->not->toHaveKey('vultr')
        ->and(app(ModuleRegistry::class)->getServerModule('vultr'))->not->toBeNull();

    $this->actingAs($admin, 'admin')->get(route('admin.config.servers'))
        ->assertOk()
        ->assertDontSee('<option value="vultr"', false)
        ->assertSee('<option value="panelica"', false);

    toggleModule($this, $admin, 'server', 'vultr', true)->assertSessionHas('success');
    expect(app(ModuleRegistry::class)->serverModuleNames())->toHaveKey('vultr');
});

test('a server module in use cannot be switched off', function () {
    Server::factory()->create(['type' => 'hestiacp']);

    toggleModule($this, modulesAdmin(), 'server', 'hestiacp', false)->assertSessionHas('error');

    expect(app(ModuleRegistry::class)->isSwitchedOff('server', 'hestiacp'))->toBeFalse();
});

test('an ssl module in use by a product cannot be switched off', function () {
    Product::factory()->create(['ssl_module' => 'gogetssl']);

    toggleModule($this, modulesAdmin(), 'ssl', 'gogetssl', false)->assertSessionHas('error');

    expect(app(ModuleRegistry::class)->sslModuleNames())->toHaveKey('gogetssl');
});

test('the servers page offers the installed server modules, not a typed-in list', function () {
    $this->actingAs(modulesAdmin(), 'admin')->get(route('admin.config.servers'))
        ->assertOk()
        ->assertSee('<option value="proxmox"', false)
        ->assertSee('<option value="hestiacp"', false)
        // CyberPanel has no module; offering it created servers nothing could drive.
        ->assertDontSee('<option value="cyberpanel"', false);
});

test('a server that already carries a type keeps it choosable when editing', function () {
    Server::factory()->create(['type' => 'cyberpanel']);

    $this->actingAs(modulesAdmin(), 'admin')->get(route('admin.config.servers'))
        ->assertSee('<option value="cyberpanel"', false);
});

test('an unknown module is a 404 and changes nothing', function () {
    toggleModule($this, modulesAdmin(), 'gateway', 'nosuchgateway', true)->assertNotFound();
    toggleModule($this, modulesAdmin(), 'widget', 'stripe', true)->assertNotFound();

    expect(GatewaySettings::where('gateway', 'nosuchgateway')->exists())->toBeFalse();
});

test('an admin without settings permission cannot reach it', function () {
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Tickets', 'permissions' => ['list_tickets']])->id,
    ]);

    $this->actingAs($admin, 'admin')->get(route('admin.config.modules'))->assertForbidden();
    toggleModule($this, $admin, 'gateway', 'banktransfer', true)->assertForbidden();
});
