<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Vultr\VultrModule;

/**
 * A usage run looking in a column the id left years ago.
 *
 * create() records the instance id with setModuleData(), which writes
 * services.module_data - the column module data was moved to precisely because
 * it used to share services.notes with the customer's own note. usageUpdate
 * still searched notes with a LIKE, so it matched nothing and a Vultr service
 * was never updated at all.
 *
 * And on the rows where it did match - a legacy row whose notes still held the
 * old JSON - it wrote the module data straight back into notes, replacing
 * whatever an operator had typed there.
 */
function vultrServer(): Server
{
    return Server::factory()->create([
        'type' => 'vultr',
        'hostname' => 'api.vultr.test',
        'ip_address' => '',
        'username' => '',
        'password' => '',
        'access_hash' => 'vultr-key',
    ]);
}

function vultrService(Server $server, array $attributes = [], array $moduleData = ['vultr_instance_id' => 'inst-1']): Service
{
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id' => $group->id,
        'server_type' => 'vultr',
        'config_options' => json_encode(['vultr_plan' => 'vc2-1c-1gb']),
    ]);

    $service = Service::factory()->create(array_merge([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'vps.test',
        'status' => 'active',
    ], $attributes));

    $service->forceFill(['module_data' => $moduleData])->save();

    return $service;
}

function vultrInstances(array $overrides = []): array
{
    return ['instances' => [array_merge([
        'id' => 'inst-1',
        'main_ip' => '203.0.113.10',
        'ram' => 1024,
        'disk' => 25,
        'vcpu_count' => 1,
    ], $overrides)]];
}

it('finds the instance by the id it recorded when it made it', function () {
    Http::fake(['*' => Http::response(vultrInstances(), 200)]);

    $server = vultrServer();
    $service = vultrService($server);

    expect((new VultrModule)->usageUpdate($server))->toBe(['updated' => 1, 'errors' => 0]);

    $fresh = $service->fresh();

    expect($fresh->disk_limit)->toBe(25 * 1024)
        ->and($fresh->module_data['vultr_main_ip'] ?? null)->toBe('203.0.113.10')
        ->and($fresh->module_data['vultr_instance_id'] ?? null)->toBe('inst-1');
});

it('leaves the note an operator typed alone', function () {
    Http::fake(['*' => Http::response(vultrInstances(), 200)]);

    $server = vultrServer();
    $service = vultrService($server, ['notes' => 'Customer asked for daily backups.']);

    (new VultrModule)->usageUpdate($server);

    expect($service->fresh()->notes)->toBe('Customer asked for daily backups.');
});

it('counts a service whose instance is gone as an error', function () {
    Http::fake(['*' => Http::response(['instances' => []], 200)]);

    $server = vultrServer();
    vultrService($server);

    expect((new VultrModule)->usageUpdate($server))->toBe(['updated' => 0, 'errors' => 1]);
});

it('says nothing happened when vultr will not answer', function () {
    Http::fake(['*' => Http::response(['error' => 'Invalid API key'], 401)]);

    $server = vultrServer();
    vultrService($server);

    expect((new VultrModule)->usageUpdate($server))->toBe(['updated' => 0, 'errors' => 1]);
});
