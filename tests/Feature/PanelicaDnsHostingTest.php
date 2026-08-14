<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * DNS zone: one authoritative zone per domain, rewritten by the panel on every
 * change. PNLCS fences to the account's own domains and keeps the records the
 * hosting depends on (SOA/NS, apex and www A) read-only, so a customer cannot
 * take their own site offline from the billing panel.
 */

function dzServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.5',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function dzService(Server $server): array
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

/** @param array $records records returned for dom-own's zone */
function fakeDnsApi(array $records = []): void
{
    Http::fake(function ($request) use ($records) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }
        if (str_contains($url, '/dns/zones/dom-own/records') && $m === 'GET') {
            return Http::response(['data' => $records], 200);
        }
        if (str_contains($url, '/dns/zones/') && $m === 'POST') {
            return Http::response(['status' => 'success', 'data' => ['id' => 'new']], 201);
        }
        if (str_contains($url, '/dns/records/') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

$TXT = ['id' => 'r-txt', 'type' => 'TXT', 'name' => '_acme', 'content' => 'token', 'ttl' => 3600];
$APEX = ['id' => 'r-apex', 'type' => 'A', 'name' => '@', 'content' => '10.0.0.5', 'ttl' => 3600];
$NS = ['id' => 'r-ns', 'type' => 'NS', 'name' => '@', 'content' => 'ns1.panel.test', 'ttl' => 3600];

it('offers the dns feature', function () {
    [$u, $s] = dzService(dzServer());
    expect((new PanelicaModule)->hostingFeatures($s))->toContain('dns');
});

it('lists records for the account\'s own domains and flags managed ones', function () use ($TXT, $APEX, $NS) {
    fakeDnsApi([$TXT, $APEX, $NS]);
    [$u, $s] = dzService(dzServer());
    $recs = collect((new PanelicaModule)->dnsRecords($s))->keyBy('id');

    expect($recs)->toHaveCount(3)
        ->and($recs['r-txt']['protected'])->toBeFalse()
        ->and($recs['r-apex']['protected'])->toBeTrue()   // apex A keeps the site up
        ->and($recs['r-ns']['protected'])->toBeTrue()     // delegation belongs to the panel
        ->and($recs['r-txt']['domain'])->toBe('example.com');
});

it('refuses a record on a domain the account does not own', function () {
    fakeDnsApi([]);
    [$u, $s] = dzService(dzServer());
    $r = (new PanelicaModule)->createDnsRecord($s, 'dom-foreign', 'TXT', 'x', 'v');
    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST');
});

it('rejects unsupported record types (SOA/NS are the panel\'s)', function () {
    fakeDnsApi([]);
    [$u, $s] = dzService(dzServer());
    expect((new PanelicaModule)->createDnsRecord($s, 'dom-own', 'NS', '@', 'ns9.test')['success'])->toBeFalse()
        ->and((new PanelicaModule)->createDnsRecord($s, 'dom-own', 'SOA', '@', 'x')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST');
});

it('refuses to overwrite the apex/www A records', function () {
    fakeDnsApi([]);
    [$u, $s] = dzService(dzServer());
    expect((new PanelicaModule)->createDnsRecord($s, 'dom-own', 'A', '@', '1.2.3.4')['success'])->toBeFalse()
        ->and((new PanelicaModule)->createDnsRecord($s, 'dom-own', 'A', 'www', '1.2.3.4')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST');
});

it('creates a normal record and sends a priority only for MX/SRV', function () {
    fakeDnsApi([]);
    [$u, $s] = dzService(dzServer());
    $mod = new PanelicaModule;

    expect($mod->createDnsRecord($s, 'dom-own', 'TXT', '_acme', 'token', 120)['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => $rq->method() === 'POST'
        && ($rq->data()['type'] ?? null) === 'TXT'
        && ($rq->data()['ttl'] ?? null) === 120
        && ! array_key_exists('priority', $rq->data()));

    expect($mod->createDnsRecord($s, 'dom-own', 'MX', '@', 'mail.example.com.', null, 20)['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => $rq->method() === 'POST'
        && ($rq->data()['type'] ?? null) === 'MX' && ($rq->data()['priority'] ?? null) === 20);
});

it('fences deletion to own records and never deletes a managed one', function () use ($TXT, $APEX) {
    fakeDnsApi([$TXT, $APEX]);
    [$u, $s] = dzService(dzServer());
    $mod = new PanelicaModule;

    expect($mod->deleteDnsRecord($s, 'r-txt')['success'])->toBeTrue()
        ->and($mod->deleteDnsRecord($s, 'r-apex')['success'])->toBeFalse()      // managed
        ->and($mod->deleteDnsRecord($s, 'r-foreign')['success'])->toBeFalse();  // not ours
    Http::assertSent(fn ($rq) => $rq->method() === 'DELETE' && str_contains($rq->url(), 'r-txt'));
    Http::assertNotSent(fn ($rq) => $rq->method() === 'DELETE' && str_contains($rq->url(), 'r-apex'));
});

it('edits one zone at a time: only the selected domain is fetched', function () use ($TXT) {
    fakeDnsApi([$TXT]);
    [$u, $s] = dzService(dzServer());
    $mod = new PanelicaModule;

    // Selected domain → that zone only.
    expect($mod->dnsRecords($s, 'dom-own'))->toHaveCount(1);
    Http::assertSent(fn ($rq) => str_contains($rq->url(), '/dns/zones/dom-own/records'));

    // A domain the account does not own resolves to nothing, and is never fetched.
    expect($mod->dnsRecords($s, 'dom-foreign'))->toBe([]);
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/dns/zones/dom-foreign/records'));
});

it('orders records by type then name so the zone is readable', function () {
    fakeDnsApi([
        ['id' => 'r1', 'type' => 'TXT', 'name' => 'b', 'content' => 'v'],
        ['id' => 'r2', 'type' => 'A', 'name' => 'shop', 'content' => '10.0.0.9'],
        ['id' => 'r3', 'type' => 'MX', 'name' => '@', 'content' => 'mail'],
        ['id' => 'r4', 'type' => 'A', 'name' => 'blog', 'content' => '10.0.0.8'],
    ]);
    [$u, $s] = dzService(dzServer());
    $types = array_column((new PanelicaModule)->dnsRecords($s, 'dom-own'), 'type');
    $names = array_column((new PanelicaModule)->dnsRecords($s, 'dom-own'), 'name');

    expect($types)->toBe(['A', 'A', 'MX', 'TXT'])
        ->and(array_slice($names, 0, 2))->toBe(['blog', 'shop']);
});

it('shows the dns tab and forbids other clients', function () use ($TXT) {
    fakeDnsApi([$TXT]);
    [$owner, $s] = dzService(dzServer());
    $this->actingAs($owner)->get(route('client.services.dns', $s))->assertOk()->assertSee('_acme');

    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($intruder)->get(route('client.services.dns', $s))->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.dns.store', $s), ['domain_id' => 'dom-own', 'type' => 'TXT', 'content' => 'v'])->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.dns.destroy', $s), ['record_id' => 'r-txt'])->assertForbidden();
});
