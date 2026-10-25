<?php

use App\Models\Service;
use Modules\Servers\Proxmox\ProxmoxModule;
use Tests\Support\FakeProxmox;

/*
 * A customer with several servers of the same product could not tell them
 * apart in the list of services: each row read "VPS Basic", and the address
 * was only on the service's own page (#287). The list now shows each
 * server's address under its name, from what the module has recorded, so the
 * page asks no server anything.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

it('shows each server\'s address in the customer\'s list of services', function () {
    $server = pveServer();
    $a = pveService($server, [], ['pve_ipv4' => '203.0.113.10']);
    $b = Service::factory()->create(['client_id' => $a->client_id, 'product_id' => $a->product_id, 'server_id' => $server->id, 'status' => 'active', 'module_data' => ['pve_seen_ip' => '203.0.113.11']]);
    $c = Service::factory()->create(['client_id' => $a->client_id, 'product_id' => $a->product_id, 'status' => 'active', 'module_data' => ['vultr_main_ip' => '198.51.100.7']]);
    $pending = Service::factory()->create(['client_id' => $a->client_id, 'product_id' => $a->product_id, 'status' => 'pending', 'module_data' => ['vultr_main_ip' => 'pending']]);

    test()->actingAs(pveCustomer($a))->get(route('client.services.index'))->assertOk()
        ->assertSee('203.0.113.10')->assertSee('203.0.113.11')->assertSee('198.51.100.7')->assertDontSee('>pending<', false);

    expect($pending->primaryIp())->toBeNull();
});

it('prefers the address from the operator\'s pool, then the one the server reported', function () {
    $s = new Service(['module_data' => ['pve_ipv4' => '203.0.113.10', 'pve_seen_ip' => '10.0.0.5']]);
    $t = new Service(['module_data' => ['pve_seen_ip' => '10.0.0.5']]);
    $u = new Service(['module_data' => ['pve_seen_ip' => 'not an ip']]);

    expect($s->primaryIp())->toBe('203.0.113.10')
        ->and($t->primaryIp())->toBe('10.0.0.5')
        ->and($u->primaryIp())->toBeNull();
});

it('keeps the first IPv4 address a running server reports, and forgets it when the server is removed', function () {
    $pve = FakeProxmox::install();
    $pve->agentRunning = true;
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 797);

    (new ProxmoxModule)->vmStatus($service->fresh(['server', 'product']));
    expect($service->fresh()->primaryIp())->toBe('192.0.2.50');

    // Asked again with nothing changed: nothing is written.
    $before = $service->fresh()->updated_at;
    test()->travel(1)->minutes();
    (new ProxmoxModule)->vmStatus($service->fresh(['server', 'product']));
    expect($service->fresh()->updated_at->equalTo($before))->toBeTrue();

    $pve->guests[797]['status'] = 'stopped';
    $pve->guests[797]['config']['protection'] = 0;
    expect((new ProxmoxModule)->terminate($service->fresh(['server', 'product']))['success'])->toBeTrue()
        ->and($service->fresh()->primaryIp())->toBeNull();
});

it('names each server by its address when a ticket is opened about it, and shows it to staff', function () {
    $server = pveServer();
    $service = pveService($server, [], ['pve_ipv4' => '203.0.113.10'], ['domain' => 'vps1.example.com']);
    $twin = Service::factory()->create(['client_id' => $service->client_id, 'product_id' => $service->product_id, 'server_id' => $server->id, 'status' => 'active', 'domain' => null, 'module_data' => ['pve_seen_ip' => '203.0.113.11']]);

    test()->actingAs(pveCustomer($service))->get(route('client.tickets.create'))->assertOk()
        ->assertSee('(vps1.example.com · 203.0.113.10)', false)->assertSee('(203.0.113.11)', false);

    test()->actingAs(pveAdmin(), 'admin')->get(route('admin.tickets.create', ['client' => $service->client_id]))->assertOk()
        ->assertSee('203.0.113.10');
});
