<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Modules\Servers\Proxmox\ProxmoxModule;
use Tests\Support\FakeProxmox;

/**
 * An upgrade the customer paid for and did not get.
 *
 * changePackage sent the new cores and memory and checked the answer, then
 * sent the disk resize and threw the answer away - it reported "VM resources
 * updated" whatever Proxmox said. Proxmox refuses a resize for ordinary
 * reasons: the storage is full, the disk cannot be shrunk, the VM is locked by
 * a running backup. The operator saw a success, the invoice went out, and the
 * disk stayed the size it was.
 *
 * usageUpdate returned zero errors no matter what. A VM that is no longer on
 * the cluster - deleted by hand on the hypervisor - was passed over in silence,
 * where every sibling module counts it.
 */
function proxmoxServer(): Server
{
    return Server::factory()->create([
        'type' => 'proxmox',
        'hostname' => 'pve.test',
        'ip_address' => '',
        'port' => 8006,
        'username' => 'root@pam',
        'password' => '',
        'access_hash' => 'PVEAPIToken=root@pam!billing=secret',
        'nameserver1' => null,
    ]);
}

function proxmoxService(Server $server, FakeProxmox $pve, bool $onCluster = true): Service
{
    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'proxmox',
        'config_options' => json_encode(['cores' => 1, 'memory' => 1024, 'disk' => 20]),
    ]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'vm.test',
        'status' => 'active',
    ]);
    $service->forceFill(['module_data' => ['proxmox_vmid' => 100, 'proxmox_node' => 'pve', 'proxmox_type' => 'qemu']])->save();

    if ($onCluster) {
        $pve->guests[100] = ['node' => 'pve', 'type' => 'qemu', 'template' => 0, 'status' => 'running', 'config' => [
            'cores' => 1, 'memory' => 1024, 'scsi0' => 'local-lvm:vm-100-disk-0,size=20G', 'boot' => 'order=scsi0',
            'tags' => 'pnlcs;pnlcs-s'.$service->id,
        ]];
    }

    return $service->fresh(['server', 'product']);
}

function biggerPlan(): array
{
    return ['config_options' => json_encode(['cores' => 4, 'memory' => 8192, 'disk' => 100])];
}

it('says so when proxmox refuses to resize the disk', function () {
    $pve = FakeProxmox::install()->fail('PUT nodes/pve/qemu/100/resize', 500, 'unable to shrink disk size');

    $result = (new ProxmoxModule)->changePackage(proxmoxService(proxmoxServer(), $pve), biggerPlan());

    expect($result['success'])->toBeFalse()
        ->and(strtolower($result['message']))->toContain('disk');
});

it('reports the upgrade only when every part of it took', function () {
    $pve = FakeProxmox::install();

    $result = (new ProxmoxModule)->changePackage(proxmoxService(proxmoxServer(), $pve), biggerPlan());

    expect($result['success'])->toBeTrue()
        ->and($pve->sent('PUT', 'nodes/pve/qemu/100/resize')[0]['params']['size'])->toBe('100G');
});

it('does not ask for a resize when the plan does not make the disk bigger', function () {
    $pve = FakeProxmox::install();

    $result = (new ProxmoxModule)->changePackage(proxmoxService(proxmoxServer(), $pve), [
        'config_options' => json_encode(['cores' => 2, 'memory' => 2048]),
    ]);

    expect($result['success'])->toBeTrue()
        ->and($pve->sent('PUT', '.*/resize'))->toBe([]);
});

it('counts a vm the cluster no longer has', function () {
    $pve = FakeProxmox::install();
    $server = proxmoxServer();
    proxmoxService($server, $pve, onCluster: false);

    expect((new ProxmoxModule)->usageUpdate($server))->toBe(['updated' => 0, 'errors' => 1]);
});

it('still counts a vm that is there as an update', function () {
    $pve = FakeProxmox::install();
    $server = proxmoxServer();
    $service = proxmoxService($server, $pve);

    expect((new ProxmoxModule)->usageUpdate($server))->toBe(['updated' => 1, 'errors' => 0]);

    expect($service->fresh()->disk_limit)->toBe(20480)
        ->and($service->fresh()->bw_usage)->toBeGreaterThanOrEqual(0);
});
