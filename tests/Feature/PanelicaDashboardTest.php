<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Phase 2 of the Panelica client hosting area: the live dashboard.
 *
 * liveUsage() gains real per-account CPU/RAM (from /v1/accounts/{id}/resource-
 * usage) and the account's domain list, on top of the disk/bandwidth/counts it
 * already returned. The CPU/RAM endpoint is newer than some panels, so these
 * tests pin BOTH the enriched happy path AND graceful degradation when that
 * endpoint is absent (404) - the dashboard must never break, it just omits the
 * gauges. Server-level metrics are never consulted (they would leak the box).
 */

function dashServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.7',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function dashService(Server $server): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'module_data' => ['panelica_user_id' => 'acct-1'],
    ]);

    return [$user, $service];
}

/** Common disk/stats/domains fakes; $resource decides the resource-usage reply. */
function fakeDashApi($resource): void
{
    Http::fake(function ($request) use ($resource) {
        $url = $request->url();
        if (str_contains($url, '/disk-usage')) {
            return Http::response(['data' => ['used_mb' => 1500, 'quota_mb' => 5120]], 200);
        }
        if (str_contains($url, '/resource-usage')) {
            return $resource;
        }
        if (str_contains($url, '/stats')) {
            return Http::response(['data' => [
                'bandwidth_mb' => 3000, 'domain_count' => 2, 'email_count' => 4, 'ftp_count' => 1, 'database_count' => 3,
            ]], 200);
        }
        if (str_contains($url, '/domains')) {
            return Http::response(['data' => [
                ['id' => 'd1', 'domain_name' => 'example.com'],
                ['id' => 'd2', 'domain_name' => 'shop.example.net'],
            ]], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

it('enriches live usage with per-account CPU and RAM', function () {
    fakeDashApi(Http::response(['data' => [
        'available' => true,
        'cpu_usage_percent' => 12.5,
        'memory_usage_mb' => 128, 'memory_limit_mb' => 512,
        'recorded_at' => '2026-08-13T06:12:46Z',
    ]], 200));
    [$user, $service] = dashService(dashServer());

    $usage = (new PanelicaModule)->liveUsage($service);

    expect($usage['cpu']['percent'])->toBe(12.5)
        ->and($usage['ram']['used_mb'])->toBe(128)
        ->and($usage['ram']['limit_mb'])->toBe(512)
        ->and($usage['ram']['percent'])->toBe(25.0);
});

it('lists the account\'s own domains on the dashboard', function () {
    fakeDashApi(Http::response(['data' => ['available' => false]], 200));
    [$user, $service] = dashService(dashServer());

    $usage = (new PanelicaModule)->liveUsage($service);

    expect($usage['domains'])->toBe(['example.com', 'shop.example.net']);
});

it('keeps the dashboard working when the CPU/RAM endpoint is absent (older panel)', function () {
    fakeDashApi(Http::response(['error' => 'not found'], 404));
    [$user, $service] = dashService(dashServer());

    $usage = (new PanelicaModule)->liveUsage($service);

    // Graceful: cpu/ram null, but the rest of the dashboard is intact.
    expect($usage['available'])->toBeTrue()
        ->and($usage['cpu'])->toBeNull()
        ->and($usage['ram'])->toBeNull()
        ->and($usage['disk']['used_mb'])->toBe(1500)
        ->and($usage['bandwidth']['used_mb'])->toBe(3000)
        ->and($usage['domains'])->toContain('example.com');
});

it('leaves CPU/RAM null for an idle account with no sample', function () {
    fakeDashApi(Http::response(['data' => ['available' => false]], 200));
    [$user, $service] = dashService(dashServer());

    $usage = (new PanelicaModule)->liveUsage($service);

    expect($usage['cpu'])->toBeNull()->and($usage['ram'])->toBeNull()
        ->and($usage['disk']['used_mb'])->toBe(1500);
});

it('serves the enriched dashboard payload to the service owner', function () {
    fakeDashApi(Http::response(['data' => [
        'available' => true, 'cpu_usage_percent' => 5.5, 'memory_usage_mb' => 64, 'memory_limit_mb' => 256,
    ]], 200));
    [$user, $service] = dashService(dashServer());

    $this->actingAs($user)
        ->getJson(route('client.services.usage', $service))
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('cpu.percent', 5.5)
        ->assertJsonPath('ram.limit_mb', 256)
        ->assertJsonPath('domains.0', 'example.com');
});
