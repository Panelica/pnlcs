<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Service;

/*
 * #308. An abuse report or a network notice often gives nothing but an IP
 * address, and the admin list of services could not be searched at all. It
 * now takes one search box: a domain, a username, a service id or an IP
 * address, matched against what the modules record (the pool or reported
 * address on Proxmox, Vultr's main IP, any further address in module_data).
 */

function sisAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('an IP address finds the service that has it, whichever module recorded it', function (array $data) {
    $hit = Service::factory()->create(['domain' => 'hit.example.com', 'module_data' => $data]);
    Service::factory()->create(['domain' => 'other.example.com', 'module_data' => ['pve_ipv4' => '203.0.113.99']]);

    $this->actingAs(sisAdmin(), 'admin')->get(route('admin.services.index', ['q' => '203.0.113.10']))->assertOk()
        ->assertSee('hit.example.com')->assertDontSee('other.example.com');
})->with([
    'pool address' => [['pve_ipv4' => '203.0.113.10']],
    'reported address' => [['pve_seen_ip' => '203.0.113.10']],
    'Vultr' => [['vultr_main_ip' => '203.0.113.10']],
    'extra addresses' => [['extra_ips' => ['198.51.100.1', '203.0.113.10']]],
]);

test('an address is matched whole, not as part of a longer one', function () {
    Service::factory()->create(['domain' => 'longer.example.com', 'module_data' => ['pve_ipv4' => '203.0.113.100']]);

    $this->actingAs(sisAdmin(), 'admin')->get(route('admin.services.index', ['q' => '203.0.113.10']))->assertOk()
        ->assertDontSee('longer.example.com');
});

test('a domain, a username or an id also finds the service, and the list shows the address', function () {
    $service = Service::factory()->create(['domain' => 'shop.example.com', 'username' => 'shopuser', 'module_data' => ['pve_ipv4' => '203.0.113.20']]);
    Service::factory()->create(['domain' => 'unrelated.example.com']);
    $admin = sisAdmin();

    foreach (['shop.example', 'shopuser', (string) $service->id] as $q) {
        $this->actingAs($admin, 'admin')->get(route('admin.services.index', ['q' => $q]))->assertOk()
            ->assertSee('shop.example.com')->assertSee('203.0.113.20')->assertDontSee('unrelated.example.com');
    }
});
