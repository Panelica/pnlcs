<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Proxmox\ProxmoxModule;

/**
 * An upgrade the customer paid for and did not get.
 *
 * changePackage sends the new cores and memory and checks the answer, then
 * sends the disk resize and throws the answer away - it reports "VM resources
 * updated" whatever Proxmox said. Proxmox refuses a resize for ordinary
 * reasons: the storage is full, the disk cannot be shrunk, the VM is locked by
 * a running backup. The operator saw a success, the invoice went out, and the
 * disk stayed the size it was.
 *
 * The password change immediately above it already checks its answer; this one
 * was left as it was.
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
    ]);
}

function proxmoxService(Server $server, array $moduleData = ['proxmox_vmid' => 100, 'proxmox_node' => 'pve', 'proxmox_type' => 'qemu']): Service
{
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id' => $group->id,
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

    $service->forceFill(['module_data' => $moduleData])->save();

    return $service;
}

function biggerPlan(): array
{
    return ['config_options' => json_encode(['cores' => 4, 'memory' => 8192, 'disk' => 100])];
}

it('says so when proxmox refuses to resize the disk', function () {
    Http::fake([
        '*/resize' => Http::response(['errors' => ['size' => 'unable to shrink disk size']], 500),
        '*' => Http::response(['data' => 'UPID:ok'], 200),
    ]);

    $server = proxmoxServer();
    $result = (new ProxmoxModule)->changePackage(proxmoxService($server), biggerPlan());

    expect($result['success'])->toBeFalse()
        ->and(strtolower($result['message']))->toContain('disk');
});

it('reports the upgrade only when every part of it took', function () {
    Http::fake(['*' => Http::response(['data' => 'UPID:ok'], 200)]);

    $server = proxmoxServer();
    $result = (new ProxmoxModule)->changePackage(proxmoxService($server), biggerPlan());

    expect($result['success'])->toBeTrue();

    $resize = collect(Http::recorded())
        ->first(fn ($pair) => str_ends_with($pair[0]->url(), '/resize'));

    expect($resize)->not->toBeNull()
        ->and($resize[0]->data()['size'])->toBe('100G');
});

it('does not ask for a resize when the plan does not name a disk', function () {
    Http::fake(['*' => Http::response(['data' => 'UPID:ok'], 200)]);

    $server = proxmoxServer();
    $result = (new ProxmoxModule)->changePackage(proxmoxService($server), [
        'config_options' => json_encode(['cores' => 2, 'memory' => 2048]),
    ]);

    expect($result['success'])->toBeTrue();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/resize'));
});

it('counts a vm the cluster no longer has', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $server = proxmoxServer();
    proxmoxService($server);

    expect((new ProxmoxModule)->usageUpdate($server))->toBe(['updated' => 0, 'errors' => 1]);
});

it('still counts a vm that is there as an update', function () {
    Http::fake(['*' => Http::response(['data' => [[
        'vmid' => 100,
        'disk' => 5368709120,
        'maxdisk' => 21474836480,
        'netin' => 1048576,
        'netout' => 1048576,
    ]]], 200)]);

    $server = proxmoxServer();
    $service = proxmoxService($server);

    expect((new ProxmoxModule)->usageUpdate($server))->toBe(['updated' => 1, 'errors' => 0]);

    expect($service->fresh()->disk_usage)->toBe(5120)
        ->and($service->fresh()->disk_limit)->toBe(20480)
        ->and($service->fresh()->bw_usage)->toBe(2);
});
