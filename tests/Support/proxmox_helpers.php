<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Tests\Support\FakeProxmox;

/*
 * Shared set-up for the Proxmox tests: a server, a service on it, a guest
 * PNLCS made for that service, and the people who look at it.
 */

function pveServer(array $settings = [], array $attrs = []): Server
{
    return Server::factory()->create(array_merge([
        'type' => 'proxmox',
        'name' => 'PVE lab',
        'hostname' => 'pve.example.com',
        'ip_address' => '',
        'port' => 8006,
        'username' => 'pnlcs@pve!billing',
        'password' => '',
        'access_hash' => '11111111-2222-3333-4444-555555555555',
        'settings' => $settings ?: null,
    ], $attrs));
}

function pveService(Server $server, array $config = [], array $moduleData = [], array $attrs = []): Service
{
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'type' => 'vps',
        'server_type' => 'proxmox',
        'config_options' => json_encode(array_merge([
            'pve_type' => 'qemu', 'pve_template' => '9000', 'pve_storage' => 'local-lvm', 'pve_bridge' => 'vmbr0',
            'pve_cores' => 2, 'pve_memory' => 2048, 'pve_disk' => 20,
        ], $config)),
    ]);

    $service = Service::factory()->create(array_merge([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'vps1.example.com',
        'status' => 'pending',
        'username' => null,
        'password' => null,
    ], $attrs));

    if ($moduleData) {
        $service->forceFill(['module_data' => $moduleData])->save();
    }

    return $service->fresh(['server', 'product']);
}

/** A guest PNLCS made for this service, as it would be after create. */
function pveOwnedGuest(FakeProxmox $pve, Service $service, int $vmid, string $type = 'qemu', string $status = 'running', array $config = []): void
{
    $pve->guests[$vmid] = [
        'node' => 'pve', 'type' => $type, 'template' => 0, 'status' => $status,
        'config' => array_merge([
            'name' => 'vps1', 'cores' => 2, 'memory' => 2048,
            ($type === 'lxc' ? 'rootfs' : 'scsi0') => "local-lvm:vm-{$vmid}-disk-0,size=20G",
            'boot' => 'order=scsi0', 'ide2' => "local-lvm:vm-{$vmid}-cloudinit,media=cdrom",
            'net0' => $type === 'lxc' ? 'name=eth0,bridge=vmbr0,hwaddr=BC:24:11:12:34:56,ip=dhcp' : 'virtio=BC:24:11:12:34:56,bridge=vmbr0',
            'tags' => 'pnlcs;pnlcs-s'.$service->id, 'protection' => 1,
            'description' => "Managed by PNLCS\npnlcs:service={$service->id}",
        ], $config),
    ];
    $service->forceFill(['module_data' => ['proxmox_vmid' => $vmid, 'proxmox_node' => 'pve', 'proxmox_type' => $type], 'status' => 'active'])->save();
}

function pveCustomer(Service $service): \App\Models\User
{
    $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);
    $user->clients()->attach($service->client_id);

    return $user;
}

function pveAdmin(): \App\Models\Admin
{
    return \App\Models\Admin::factory()->create(['role_id' => \App\Models\AdminRole::factory()->fullAdmin()->create()->id]);
}
