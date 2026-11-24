<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Http;

/*
 * The registrar select on a domain page offered every module the installation
 * had, whether the operator had switched it on or not. The domain list and the
 * client's add-domain form already used only the active registrars.
 */

function registrarOptionsAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function registrarOptionsDomain(string $registrar): Domain
{
    return Domain::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'registrar-options.example',
        'registrar' => $registrar,
        'status' => 'active',
        'expiry_date' => now()->addYear(),
        'next_due_date' => now()->addYear(),
    ]);
}

beforeEach(function () {
    RegistrarSettings::where('setting', 'visible')->delete();

    RegistrarSettings::create(['registrar' => 'hrd', 'setting' => 'visible', 'value' => '1']);
    RegistrarSettings::create(['registrar' => 'ovh', 'setting' => 'visible', 'value' => '1']);
});

it('offers only active registrars in the domain select', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $domain = registrarOptionsDomain('Manual');

    test()->actingAs(registrarOptionsAdmin(), 'admin')
        ->get(route('admin.domains.show', $domain))
        ->assertOk()
        ->assertSee('value="HRD"', false)
        ->assertSee('value="OVH"', false)
        ->assertSee('value="Manual"', false)
        ->assertDontSee('value="Namecheap"', false)
        ->assertDontSee('value="OpenProvider"', false)
        ->assertDontSee('value="DomainNameAPI"', false);
});

it('keeps an inactive current registrar as a marked exception', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $domain = registrarOptionsDomain('Namecheap');

    test()->actingAs(registrarOptionsAdmin(), 'admin')
        ->get(route('admin.domains.show', $domain))
        ->assertOk()
        ->assertSee('value="Namecheap"', false)
        ->assertSee('Namecheap ('.__('common.status.inactive').')');

    test()->actingAs(registrarOptionsAdmin(), 'admin')
        ->post(route('admin.domains.registrar', $domain), ['registrar' => 'Namecheap'])
        ->assertSessionHas('success');

    expect($domain->fresh()->registrar)->toBe('Namecheap');
});

it('refuses switching to an inactive registrar', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $domain = registrarOptionsDomain('Manual');

    test()->actingAs(registrarOptionsAdmin(), 'admin')
        ->post(route('admin.domains.registrar', $domain), ['registrar' => 'Namecheap'])
        ->assertSessionHasErrors('registrar');

    expect($domain->fresh()->registrar)->toBe('Manual');
});

it('hides Manual when the operator switches it off', function () {
    Http::fake(['*' => Http::response([], 200)]);
    RegistrarSettings::create(['registrar' => 'manual', 'setting' => 'visible', 'value' => '0']);
    $domain = registrarOptionsDomain('HRD');

    test()->actingAs(registrarOptionsAdmin(), 'admin')
        ->get(route('admin.domains.show', $domain))
        ->assertOk()
        ->assertDontSee('value="Manual"', false);
});
