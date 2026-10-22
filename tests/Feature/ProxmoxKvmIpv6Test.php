<?php

use Modules\Servers\Proxmox\ProxmoxModule;
use Tests\Support\FakeProxmox;

require_once __DIR__.'/../Support/proxmox_helpers.php';

/**
 * The product's IPv6 setting is offered for both guest types, but only
 * containers applied it (lxcNet adds ip6=auto); a KVM guest's cloud-init
 * ipconfig0 never got it.
 */
it('gives a KVM guest IPv6 when the product asks for it', function () {
    $pve = FakeProxmox::install()->template(9000);
    $service = pveService(pveServer(), ['pve_ipv6' => 'auto']);

    $result = (new ProxmoxModule)->create($service);

    expect($result['success'])->toBeTrue($result['message'])
        ->and($pve->guests[$result['data']['vmid']]['config']['ipconfig0'])->toBe('ip=dhcp,ip6=auto');
});

it('leaves IPv6 off a KVM guest by default', function () {
    $pve = FakeProxmox::install()->template(9000);

    $result = (new ProxmoxModule)->create(pveService(pveServer()));

    expect($pve->guests[$result['data']['vmid']]['config']['ipconfig0'])->toBe('ip=dhcp');
});
