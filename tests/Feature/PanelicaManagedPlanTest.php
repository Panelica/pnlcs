<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/**
 * Full Panelica parity: a "managed" PNLCS product provisions by creating/syncing
 * a panel plan whose columns match the Panelica WHMCS module contract exactly
 * (POST /v1/plans basic + PATCH /v1/plans/{id} advanced), then creating the
 * account with that plan.
 */

function panelicaServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.1',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk_test_key',
        'access_hash' => 'sk_test_secret', 'port' => 8443, 'active' => true,
    ]);
}

it('sends the exact managed-plan resource payload to the panel and provisions with it', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        $method = $request->method();
        if (str_contains($url, '/v1/plans/')) {
            return Http::response(['data' => ['id' => 'plan-xyz']], 200);          // PATCH
        }
        if (str_contains($url, '/v1/plans')) {
            return $method === 'GET'
                ? Http::response(['data' => []], 200)                               // no existing plan
                : Http::response(['data' => ['id' => 'plan-xyz']], 200);            // POST create
        }
        if (str_contains($url, '/v1/accounts')) {
            return Http::response(['data' => ['id' => 'acct-1']], 200);
        }
        if (str_contains($url, '/v1/domains')) {
            return Http::response(['data' => ['id' => 'dom-1']], 200);
        }
        return Http::response([], 200);
    });

    $server  = panelicaServer();
    $client  = Client::factory()->create();
    $product = Product::factory()->create([
        'server_type' => 'panelica',
        'config_options' => json_encode([
            'res_managed'     => 1,
            'res_disk_mb'     => 2048,
            'res_bandwidth_mb'=> 40000,
            'res_max_domains' => 3,
            'res_cpu_percent' => 200,
            'res_memory_mb'   => 2048,
            'res_inode_quota' => 250000,
            'res_io_mbs'      => 50,
            'res_iops'        => 1000,
            'res_ssh_level'   => 'jailed',
        ]),
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id,
        'server_id' => $server->id, 'domain' => 'buyer.example.com', 'status' => 'pending',
    ]);

    $result = (new PanelicaModule())->create($service);
    expect($result['success'] ?? false)->toBeTrue();

    // Basic columns via POST /v1/plans
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/plans')
        && $r->method() === 'POST'
        && ($r->data()['disk_quota_mb'] ?? null) === 2048
        && ($r->data()['max_domains'] ?? null) === 3
        && ($r->data()['ssh_access_enabled'] ?? null) === true);

    // Advanced columns via PATCH /v1/plans/{id} — cgroups/quota/iops parity
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/plans/')
        && $r->method() === 'PATCH'
        && ($r->data()['cpu_limit_percent'] ?? null) === 200
        && ($r->data()['memory_limit_mb'] ?? null) === 2048
        && ($r->data()['inode_quota'] ?? null) === 250000
        && ($r->data()['iops_limit'] ?? null) === 1000
        && ($r->data()['io_read_bps'] ?? null) === 50 * 1048576
        && ($r->data()['ssh_access_level'] ?? null) === 'jailed');

    // Account created with the managed plan id
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/accounts')
        && $r->method() === 'POST'
        && ($r->data()['plan_id'] ?? null) === 'plan-xyz');
});

it('falls back to panelica_plan_id when the product is not managed', function () {
    Http::fake([
        '*/v1/accounts' => Http::response(['data' => ['id' => 'a1']], 200),
        '*/v1/domains'  => Http::response(['data' => ['id' => 'd1']], 200),
    ]);

    $server  = panelicaServer();
    $client  = Client::factory()->create();
    $product = Product::factory()->create([
        'server_type' => 'panelica',
        'config_options' => json_encode(['panelica_plan_id' => 'existing-plan-1']),
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id,
        'server_id' => $server->id, 'domain' => 'nomanage.example.com', 'status' => 'pending',
    ]);

    (new PanelicaModule())->create($service);

    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/v1/plans'));  // no managed plan created
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/accounts')
        && ($r->data()['plan_id'] ?? null) === 'existing-plan-1');
});
