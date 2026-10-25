<?php

use Tests\Support\FakeProxmox;

/*
 * The customer's VPS panel polls a status endpoint that returned, along with
 * the figures the panel shows, the name of the Proxmox node the server runs
 * on. The panel never displays it; it only told every customer the name of the
 * operator's host. Staff still get it.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

it('leaves the node out of the customer\'s status and keeps it for staff', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 796);

    test()->actingAs(pveCustomer($service))->getJson(route('client.services.vps.status', $service))
        ->assertOk()->assertJsonPath('available', true)->assertJsonPath('vmid', 796)->assertJsonMissingPath('node');

    test()->actingAs(pveAdmin(), 'admin')->getJson(route('admin.services.vps.status', $service))
        ->assertOk()->assertJsonPath('node', 'pve');
});
