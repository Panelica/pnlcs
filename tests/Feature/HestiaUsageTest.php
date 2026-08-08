<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\HestiaCP\HestiaCPModule;

/**
 * What HestiaCP answers, and what this module made of it.
 *
 * v-list-users names its fields the way HestiaCP's own bin/v-list-users writes
 * them: DISK_QUOTA and BANDWIDTH are the limits, U_DISK and U_BANDWIDTH are
 * what the account has used. This module read DISK_USED - a field that does not
 * exist - for disk usage, and BANDWIDTH for bandwidth usage.
 *
 * So the disk figure was never recorded at all, and every account was shown
 * using its entire bandwidth allowance from the day it was created: a 10 GB
 * plan reported 10 GB used while the account was empty. Anything that reads
 * those figures - the usage bars, the overage billing, the limit warnings - was
 * reading the plan back to itself.
 */
function hestiaServer(): Server
{
    return Server::factory()->create([
        'type' => 'hestiacp',
        'hostname' => 'hestia.test',
        'ip_address' => '',
        'port' => 8083,
        'username' => 'admin',
        'password' => 'admin-secret',
        'access_hash' => '',
    ]);
}

function hestiaService(Server $server, array $attributes = [], array $moduleData = ['hestia_username' => 'shopuser']): Service
{
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id' => $group->id,
        'server_type' => 'hestiacp',
        'config_options' => json_encode(['package_name' => 'default']),
    ]);

    $service = Service::factory()->create(array_merge([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.test',
        'status' => 'active',
        'username' => 'shopuser',
    ], $attributes));

    $service->forceFill(['module_data' => $moduleData])->save();

    return $service;
}

function hestiaListing(array $overrides = []): string
{
    return json_encode(['shopuser' => array_merge([
        'PACKAGE' => 'default',
        'DISK_QUOTA' => '1024',
        'BANDWIDTH' => '10240',
        'U_DISK' => '512',
        'U_BANDWIDTH' => '2048',
        'SUSPENDED' => 'no',
    ], $overrides)]);
}

it('records what a hestia account has used, not what it is allowed', function () {
    Http::fake(['*' => Http::response(hestiaListing(), 200)]);

    $server = hestiaServer();
    $service = hestiaService($server);

    expect((new HestiaCPModule)->usageUpdate($server))->toBe(['updated' => 1, 'errors' => 0]);

    $fresh = $service->fresh();

    expect($fresh->disk_usage)->toBe(512)
        ->and($fresh->bw_usage)->toBe(2048)
        ->and($fresh->disk_limit)->toBe(1024)
        ->and($fresh->bw_limit)->toBe(10240);
});

it('finds the account by the name hestia knows it by', function () {
    Http::fake(['*' => Http::response(hestiaListing(), 200)]);

    $server = hestiaServer();
    // The service was renamed in the panel; the account on the server is not.
    $service = hestiaService($server, ['username' => 'renamed'], ['hestia_username' => 'shopuser']);

    expect((new HestiaCPModule)->usageUpdate($server))->toBe(['updated' => 1, 'errors' => 0]);
    expect($service->fresh()->disk_usage)->toBe(512);
});

it('counts an account hestia does not list as an error', function () {
    Http::fake(['*' => Http::response(json_encode(['someoneelse' => ['U_DISK' => '1']]), 200)]);

    $server = hestiaServer();
    hestiaService($server);

    expect((new HestiaCPModule)->usageUpdate($server))->toBe(['updated' => 0, 'errors' => 1]);
});

it('leaves an unlimited allowance alone rather than storing it as zero', function () {
    Http::fake(['*' => Http::response(hestiaListing([
        'DISK_QUOTA' => 'unlimited',
        'BANDWIDTH' => 'unlimited',
    ]), 200)]);

    $server = hestiaServer();
    $service = hestiaService($server, ['disk_limit' => 250, 'bw_limit' => 500]);

    (new HestiaCPModule)->usageUpdate($server);

    $fresh = $service->fresh();

    expect($fresh->disk_limit)->toBe(250)
        ->and($fresh->bw_limit)->toBe(500)
        ->and($fresh->disk_usage)->toBe(512);
});

it('still says nothing happened when hestia will not answer', function () {
    Http::fake(['*' => Http::response('Error: user does not exist', 200)]);

    $server = hestiaServer();
    hestiaService($server);

    expect((new HestiaCPModule)->usageUpdate($server))->toBe(['updated' => 0, 'errors' => 1]);
});
