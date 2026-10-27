<?php

use App\Models\Client;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Tests\Support\FakeProxmox;

/*
 * #322. The client area's Server Information showed the name staff gave the
 * Proxmox server ("PROXMOX UA1"). Its address was already left out - a
 * virtual server's host is the hypervisor, not the customer's business - but
 * the name was not. A virtual server now shows the customer their own
 * server's address instead; other services still show their server.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

it('does not name the Proxmox server to the customer, and shows the VPS address instead', function () {
    $pve = FakeProxmox::install();
    $server = pveServer();
    $server->update(['name' => 'PROXMOX UA1 internal']);
    $service = pveService($server, [], [], ['domain' => 'vps1.example.com']);
    pveOwnedGuest($pve, $service, 795);
    $service->forceFill(['module_data' => $service->module_data + ['pve_ipv4' => '203.0.113.44']])->save();

    $this->actingAs(pveCustomer($service))->get(route('client.services.show', $service))->assertOk()
        ->assertDontSee('PROXMOX UA1 internal')
        ->assertSee('203.0.113.44');
});

it('still names the server of a hosting account', function () {
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $server = Server::factory()->create(['name' => 'web-eu-1', 'type' => 'panelica']);
    $service = Service::factory()->create(['client_id' => $client->id, 'server_id' => $server->id, 'status' => 'active']);

    $this->actingAs($user)->get(route('client.services.show', $service))->assertOk()->assertSee('web-eu-1');
});
