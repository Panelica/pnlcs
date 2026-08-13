<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

function panelServerUP(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.1',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function usageService(Server $server): array
{
    $client = Client::factory()->create();
    $user   = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'notes' => json_encode(['panelica_user_id' => 'acct-1']),
    ]);
    return [$user, $service];
}

// ---- Plan dropdown ----
it('shows the panel plan dropdown on the product edit page', function () {
    Http::fake(['*/v1/plans' => Http::response(['data' => [
        ['id' => 'p1', 'name' => 'Starter'], ['id' => 'p2', 'name' => 'Pro'],
    ]], 200)]);
    panelServerUP();
    $admin   = Admin::factory()->create();
    $product = Product::factory()->create(['server_type' => 'panelica']);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee('name="panelica_plan_id"', false)
        ->assertSee('Starter')
        ->assertSee('Pro');
});

// ---- Live usage endpoint ----
it('returns live disk/bandwidth/counts usage for the client service', function () {
    Http::fake([
        '*/v1/accounts/*/disk-usage' => Http::response(['data' => ['used_mb' => 1500, 'quota_mb' => 5120]], 200),
        '*/v1/accounts/*/resource-usage' => Http::response(['data' => ['available' => false]], 200),
        '*/v1/accounts/*/domains' => Http::response(['data' => []], 200),
        '*/v1/accounts/*/stats'      => Http::response(['data' => [
            'bandwidth_mb' => 3000, 'domain_count' => 2, 'email_count' => 4, 'ftp_count' => 1, 'database_count' => 3,
        ]], 200),
    ]);
    [$user, $service] = usageService(panelServerUP());

    $this->actingAs($user)
        ->getJson(route('client.services.usage', $service))
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('disk.used_mb', 1500)
        ->assertJsonPath('disk.quota_mb', 5120)
        ->assertJsonPath('bandwidth.used_mb', 3000)
        ->assertJsonPath('counts.domains', 2)
        ->assertJsonPath('counts.databases', 3);
});

it('forbids usage of another client service', function () {
    [$user, $service] = usageService(panelServerUP());
    $other  = User::factory()->create();
    $oc     = Client::factory()->create();
    $other->clients()->attach($oc->id);

    $this->actingAs($other)
        ->getJson(route('client.services.usage', $service))
        ->assertForbidden();
});

// ---- usageUpdate cron mapping fix ----
it('usageUpdate populates disk + bandwidth from the correct panel endpoints', function () {
    Http::fake([
        '*/v1/accounts/*/disk-usage' => Http::response(['data' => ['used_mb' => 800, 'quota_mb' => 2048]], 200),
        '*/v1/accounts/*/stats'      => Http::response(['data' => ['bandwidth_mb' => 1200]], 200),
    ]);
    $server = panelServerUP();
    $client = Client::factory()->create();
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'notes' => json_encode(['panelica_user_id' => 'acct-1']),
    ]);

    (new PanelicaModule())->usageUpdate($server);

    $service->refresh();
    expect((int) $service->disk_usage)->toBe(800)
        ->and((int) $service->disk_limit)->toBe(2048)
        ->and((int) $service->bw_usage)->toBe(1200);
});
