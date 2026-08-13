<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Subdomains: per parent-domain, fenced to the account's own domains; creation
 * gated by the plan's max_subdomains (the panel also enforces it, and actually
 * provisions the vhost). These tests pin the PNLCS fence + policy gate +
 * ownership + controller gates.
 */

function sdServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.3',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function sdService(Server $server): array
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

/** $max = plan max_subdomains; $subs = existing subdomains under dom-own. */
function fakeSdApi(int $max, array $subs = []): void
{
    Http::fake(function ($request) use ($max, $subs) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '') && $m === 'GET') {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans') && $m === 'GET') {
            return Http::response(['data' => [['id' => 'plan-1', 'max_subdomains' => $max]]], 200);
        }
        if (str_contains($url, '/subdomains') && $m === 'GET') {
            return Http::response(['data' => $subs], 200);
        }
        if (str_contains($url, '/subdomains') && $m === 'POST') {
            return Http::response(['data' => ['id' => 'new']], 201);
        }
        if (str_contains($url, '/v1/subdomains/') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

$SUB = ['id' => 's-own', 'subdomain_name' => 'blog', 'full_name' => 'blog.example.com', 'document_root' => '/x', 'ssl_enabled' => true, 'status' => 'active'];

it('offers the subdomains feature', function () {
    [$u, $s] = sdService(sdServer());
    expect((new PanelicaModule)->hostingFeatures($s))->toContain('subdomains');
});

it('lists subdomains under the account\'s own domains', function () use ($SUB) {
    fakeSdApi(5, [$SUB]);
    [$u, $s] = sdService(sdServer());
    $list = (new PanelicaModule)->subdomains($s);
    expect($list)->toHaveCount(1)->and($list[0]['full_name'])->toBe('blog.example.com')->and($list[0]['domain'])->toBe('example.com');
});

it('reads the plan subdomain policy', function () use ($SUB) {
    fakeSdApi(3, [$SUB]);
    [$u, $s] = sdService(sdServer());
    $p = (new PanelicaModule)->subdomainPolicy($s);
    expect($p['max'])->toBe(3)->and($p['used'])->toBe(1)->and($p['can_create'])->toBeTrue();
});

it('refuses to create when the plan limit is reached — no request sent', function () use ($SUB) {
    fakeSdApi(1, [$SUB]);
    [$u, $s] = sdService(sdServer());
    $r = (new PanelicaModule)->createSubdomain($s, 'dom-own', 'shop');
    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/subdomains'));
});

it('refuses a subdomain on a domain the account does not own', function () {
    fakeSdApi(5, []);
    [$u, $s] = sdService(sdServer());
    $r = (new PanelicaModule)->createSubdomain($s, 'dom-foreign', 'shop');
    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST');
});

it('creates a subdomain on an owned domain when under the limit', function () {
    fakeSdApi(5, []);
    [$u, $s] = sdService(sdServer());
    $r = (new PanelicaModule)->createSubdomain($s, 'dom-own', 'blog', null, '8.3', true);
    expect($r['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/domains/dom-own/subdomains'));
});

it('fences subdomain deletion to the account\'s own subdomains', function () use ($SUB) {
    fakeSdApi(5, [$SUB]);
    [$u, $s] = sdService(sdServer());
    $mod = new PanelicaModule;
    expect($mod->deleteSubdomain($s, 's-own')['success'])->toBeTrue()
        ->and($mod->deleteSubdomain($s, 's-foreign')['success'])->toBeFalse();
});

it('shows the subdomains tab and forbids other clients', function () use ($SUB) {
    fakeSdApi(5, [$SUB]);
    [$owner, $s] = sdService(sdServer());
    $this->actingAs($owner)->get(route('client.services.subdomains', $s))->assertOk()->assertSee('blog.example.com');

    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($intruder)->get(route('client.services.subdomains', $s))->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.subdomains.store', $s), ['domain_id' => 'dom-own', 'name' => 'x'])->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.subdomains.destroy', $s), ['subdomain_id' => 's-own'])->assertForbidden();
});
