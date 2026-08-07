<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Modules\Servers\CPanel\CPanelModule;
use Modules\Servers\Plesk\PleskModule;

/**
 * What Plesk's own API actually accepts.
 *
 * Checked against the openapi.yml shipped with Plesk Obsidian 18.0.77:
 *  - /clients/{id}/suspend and /clients/{id}/activate define PUT and nothing
 *    else, so the POSTs this module sent could only ever have come back 405.
 *  - the API authenticates with X-API-Key or with HTTP Basic. The server form
 *    asks for a username and a password and calls the API key "Optional", so
 *    an operator who filled it in the ordinary way was never authenticated at
 *    all.
 *  - a client password is documented as 5 to 14 characters; the module made
 *    one of 21.
 *  - there is no plans endpoint in the REST API. The product form's plan
 *    picker has to come from the XML API, which is where WHMCS reads it too.
 */
function pleskServer(array $attributes = []): Server
{
    return Server::factory()->create(array_merge([
        'type' => 'plesk',
        'hostname' => 'plesk.test',
        'ip_address' => '',
        'port' => 8443,
        'username' => 'admin',
        'password' => 'admin-secret',
        'access_hash' => 'sk-123',
    ], $attributes));
}

/**
 * The request that went to a particular endpoint.
 */
function requestTo(string $path)
{
    $pair = collect(Http::recorded())
        ->first(fn ($pair) => str_ends_with($pair[0]->url(), $path));

    expect($pair)->not->toBeNull();

    return $pair[0];
}

function pleskService(Server $server, array $moduleData = ['plesk_client_id' => 'c-1', 'plesk_domain_id' => 'd-1']): Service
{
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id' => $group->id,
        'server_type' => 'plesk',
        'config_options' => json_encode(['package_name' => 'Basic']),
    ]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.test',
        'status' => 'active',
    ]);

    $service->forceFill(['module_data' => $moduleData])->save();

    return $service;
}

it('suspends with the verb plesk defines', function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    $server = pleskServer();

    (new PleskModule)->suspend(pleskService($server), 'unpaid');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/clients/c-1/suspend')
        && $request->method() === 'PUT');
});

it('unsuspends with the verb plesk defines', function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    $server = pleskServer();

    (new PleskModule)->unsuspend(pleskService($server));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/clients/c-1/activate')
        && $request->method() === 'PUT');
});

it('authenticates with the api key when the operator gave one', function () {
    Http::fake(['*' => Http::response(['version' => '18.0.77'], 200)]);

    (new PleskModule)->testConnection(pleskServer());

    Http::assertSent(fn ($request) => $request->header('X-API-Key')[0] === 'sk-123');
});

it('falls back to the administrator login when there is no api key', function () {
    Http::fake(['*' => Http::response(['version' => '18.0.77'], 200)]);

    (new PleskModule)->testConnection(pleskServer(['access_hash' => '']));

    Http::assertSent(function ($request) {
        $authorization = $request->header('Authorization')[0] ?? '';

        return $authorization === 'Basic '.base64_encode('admin:admin-secret')
            && ($request->header('X-API-Key')[0] ?? '') === '';
    });
});

it('gives the plesk client a password plesk will accept', function () {
    Http::fake([
        '*/api/v2/clients' => Http::response(['id' => 'c-9'], 201),
        '*/api/v2/domains' => Http::response(['id' => 'd-9'], 201),
    ]);

    $server = pleskServer();
    $service = pleskService($server, []);
    $service->forceFill(['password' => null])->save();

    expect((new PleskModule)->create($service)['success'])->toBeTrue();

    // The one request that carries the password, not "any request that does
    // not": assertSent is satisfied by whichever call answers first.
    $password = requestTo('/api/v2/clients')->data()['password'] ?? '';

    expect(strlen($password))->toBeGreaterThanOrEqual(5)
        ->and(strlen($password))->toBeLessThanOrEqual(14)
        ->and($password)->toMatch('/[a-z]/')
        ->and($password)->toMatch('/[A-Z]/')
        ->and($password)->toMatch('/\d/');
});

it('offers the service plans the plesk server has', function () {
    $xml = <<<'XML'
<?xml version="1.0"?>
<packet version="1.6.9.1">
  <service-plan>
    <get>
      <result><status>ok</status><id>1</id><name>Unlimited</name></result>
      <result><status>ok</status><id>2</id><name>Basic hosting</name></result>
    </get>
  </service-plan>
</packet>
XML;

    Http::fake(['*/enterprise/control/agent.php' => Http::response($xml, 200)]);

    $plans = (new PleskModule)->listPackages(pleskServer());

    expect(array_column($plans, 'id'))->toBe(['Basic hosting', 'Unlimited']);
});

it('says nothing rather than guessing when plesk will not list its plans', function () {
    Http::fake(['*' => Http::response('Unauthorized', 401)]);

    expect((new PleskModule)->listPackages(pleskServer()))->toBe([]);
});

it('still creates the subscription the way it did', function () {
    Http::fake([
        '*/api/v2/clients' => Http::response(['id' => 'c-9'], 201),
        '*/api/v2/domains' => Http::response(['id' => 'd-9'], 201),
    ]);

    $server = pleskServer();
    $result = (new PleskModule)->create(pleskService($server, []));

    expect($result['success'])->toBeTrue()
        ->and($result['data']['plesk_client_id'])->toBe('c-9');

    $subscription = requestTo('/api/v2/domains');
    $body = $subscription->data();

    expect($subscription->method())->toBe('POST')
        ->and($body['hosting_type'])->toBe('virtual')
        ->and($body['hosting_settings']['ftp_login'] ?? '')->not->toBe('')
        ->and($body['plan']['name'] ?? '')->toBe('Basic');
});

it('authenticates with whm the api token when there is one', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1]], 200)]);

    $server = Server::factory()->create([
        'type' => 'cpanel', 'hostname' => 'whm.test', 'ip_address' => '',
        'port' => 2087, 'username' => 'root', 'password' => 'root-secret', 'access_hash' => 'tok-1',
    ]);

    (new CPanelModule)->testConnection($server);

    Http::assertSent(fn ($request) => $request->header('Authorization')[0] === 'whm root:tok-1');
});

it('falls back to the whm password when no token was given', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1]], 200)]);

    $server = Server::factory()->create([
        'type' => 'cpanel', 'hostname' => 'whm.test', 'ip_address' => '',
        'port' => 2087, 'username' => 'root', 'password' => 'root-secret', 'access_hash' => '',
    ]);

    (new CPanelModule)->testConnection($server);

    Http::assertSent(fn ($request) => $request->header('Authorization')[0] === 'Basic '.base64_encode('root:root-secret'));
});
