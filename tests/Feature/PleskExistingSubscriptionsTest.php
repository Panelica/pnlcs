<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Plesk\PleskModule;

/*
 * Moving customers who already run on a Plesk server into PNLCS.
 *
 * The operator records a billing service and links it to the subscription that
 * is already there, picked from the server's list. From then on the service
 * acts on that one subscription: a Plesk customer usually owns several, and
 * suspending or deleting the customer took all of them down for one service.
 */
const PLESK_AGENT = 'https://plesk.test:8443/enterprise/control/agent.php';

function existingSubsServer(): Server
{
    return Server::factory()->create([
        'type' => 'plesk', 'hostname' => 'plesk.test', 'ip_address' => '', 'port' => 8443,
        'username' => 'admin', 'password' => 'admin-secret', 'access_hash' => 'sk-123',
    ]);
}

function existingSubsAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function existingSubsService(Server $server, array $moduleData): Service
{
    $service = Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => Product::factory()->create([
            'group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'plesk',
        ])->id,
        'server_id' => $server->id,
        'domain' => 'shop.test',
        'status' => 'active',
    ]);
    $service->forceFill(['module_data' => $moduleData])->save();

    return $service;
}

/**
 * The XML API answers one packet per call; each operation gets its own reply.
 *
 * @param  array<string, string>  $replies  operation tag (webspace-get, customer-get, webspace-set...) => inner XML
 */
function fakePleskAgent(array $replies): void
{
    Http::preventStrayRequests();
    Http::fake([PLESK_AGENT => function (Request $request) use ($replies) {
        $body = $request->body();
        $out = '';
        foreach (['webspace', 'customer', 'reseller'] as $object) {
            foreach (['get', 'set', 'del'] as $verb) {
                if (str_contains($body, "<{$object}><{$verb}>")) {
                    $out .= "<{$object}><{$verb}>".($replies["{$object}-{$verb}"] ?? '')."</{$verb}></{$object}>";
                }
            }
        }

        return Http::response('<?xml version="1.0"?><packet>'.$out.'</packet>', 200);
    }]);
}

function webspaceResult(int $id, string $name, int $ownerId, int $status = 0): string
{
    return "<result><status>ok</status><id>{$id}</id><data><gen_info><name>{$name}</name>"
        ."<owner-id>{$ownerId}</owner-id><status>{$status}</status></gen_info></data></result>";
}

function customerResult(int $id, string $login, string $company, string $email): string
{
    return "<result><status>ok</status><id>{$id}</id><data><gen_info><login>{$login}</login>"
        ."<cname>{$company}</cname><pname></pname><email>{$email}</email></gen_info></data></result>";
}

it('lists the subscriptions on the server with who owns them', function () {
    fakePleskAgent([
        'webspace-get' => webspaceResult(12, 'rossi.it', 5, 16).webspaceResult(11, 'bianchi.com', 5),
        'customer-get' => customerResult(5, 'rossi', 'Rossi Srl', 'info@rossi.it'),
    ]);

    $accounts = $this->actingAs(existingSubsAdmin(), 'admin')
        ->get(route('admin.clients.server-accounts', existingSubsServer()))
        ->assertOk()
        ->json('accounts');

    expect($accounts)->toBe([
        ['id' => '11', 'username' => 'bianchi.com · Rossi Srl', 'email' => 'info@rossi.it', 'status' => 'active'],
        ['id' => '12', 'username' => 'rossi.it · Rossi Srl', 'email' => 'info@rossi.it', 'status' => 'suspended'],
    ]);
});

it('names the reseller that owns a subscription', function () {
    fakePleskAgent([
        'webspace-get' => webspaceResult(12, 'rossi.it', 7),
        'reseller-get' => '<result><status>ok</status><id>7</id><data><gen-info><login>webco</login>'
            .'<cname>Web Co</cname><pname></pname><email>info@webco.it</email></gen-info></data></result>',
    ]);

    $accounts = $this->actingAs(existingSubsAdmin(), 'admin')
        ->get(route('admin.clients.server-accounts', existingSubsServer()))
        ->assertOk()
        ->json('accounts');

    expect($accounts)->toBe([
        ['id' => '12', 'username' => 'rossi.it · Web Co', 'email' => 'info@webco.it', 'status' => 'active'],
    ]);
    Http::assertSent(fn (Request $request) => str_contains($request->body(), '<reseller><get><filter><all/></filter><dataset><gen-info/>'));
});

it('links a recorded service to the subscription and its owner', function () {
    fakePleskAgent([
        'webspace-get' => webspaceResult(12, 'rossi.it', 5),
        'customer-get' => customerResult(5, 'rossi', 'Rossi Srl', 'info@rossi.it'),
    ]);
    $client = Client::factory()->create();
    $server = existingSubsServer();

    $this->actingAs(existingSubsAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'product_id' => Product::factory()->create([
                'group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'plesk',
            ])->id,
            'server_id' => $server->id,
            'billing_cycle' => 'Annually',
            'amount' => '60',
            'status' => 'active',
            'link_user_id' => '12',
        ])
        ->assertSessionHas('success');

    $service = Service::where('client_id', $client->id)->sole();

    expect($service->module_data)->toEqual(['plesk_domain_id' => '12', 'plesk_client_id' => '5'])
        ->and($service->username)->toBe('rossi')
        ->and($service->domain)->toBe('rossi.it');
});

it('links a subscription the administrator owns without a customer', function () {
    fakePleskAgent(['webspace-get' => webspaceResult(12, 'esalogica.it', 1)]);
    $service = existingSubsService(existingSubsServer(), []);

    expect((new PleskModule)->linkAccount($service, '12')['success'])->toBeTrue()
        ->and($service->fresh()->module_data)->toBe(['plesk_domain_id' => '12']);
});

it('does not link a subscription the server does not have', function () {
    fakePleskAgent(['webspace-get' => '<result><status>error</status><errcode>1013</errcode><errtext>Object not found</errtext></result>']);
    $service = existingSubsService(existingSubsServer(), []);

    expect((new PleskModule)->linkAccount($service, '99')['success'])->toBeFalse()
        ->and($service->fresh()->module_data)->toBe([]);
});

it('does not record a second service for a subscription another one manages', function () {
    Http::preventStrayRequests();
    $server = existingSubsServer();
    $existing = existingSubsService($server, ['plesk_domain_id' => '12', 'plesk_client_id' => '5']);
    $client = Client::factory()->create();

    $this->actingAs(existingSubsAdmin(), 'admin')
        ->post(route('admin.clients.services.store', $client), [
            'product_id' => $existing->product_id,
            'server_id' => $server->id,
            'billing_cycle' => 'Annually',
            'amount' => '60',
            'status' => 'active',
            'link_user_id' => '12',
        ])
        ->assertSessionHas('error');

    expect(Service::where('client_id', $client->id)->exists())->toBeFalse();
});

it('suspends and reactivates only the subscription', function (string $action, int $status) {
    fakePleskAgent(['webspace-set' => '<result><status>ok</status><id>12</id></result>']);
    $service = existingSubsService(existingSubsServer(), ['plesk_domain_id' => '12', 'plesk_client_id' => '5']);

    $result = $action === 'suspend' ? (new PleskModule)->suspend($service) : (new PleskModule)->unsuspend($service);

    expect($result['success'])->toBeTrue();
    Http::assertSent(fn (Request $request) => str_contains($request->body(), '<filter><id>12</id></filter>')
        && str_contains($request->body(), "<status>{$status}</status>"));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/clients/'));
})->with([
    'suspend' => ['suspend', 16],
    'unsuspend' => ['unsuspend', 0],
]);

it('reports what plesk said when it refuses to suspend', function () {
    fakePleskAgent(['webspace-set' => '<result><status>error</status><errcode>1013</errcode><errtext>Object not found</errtext></result>']);
    $service = existingSubsService(existingSubsServer(), ['plesk_domain_id' => '12']);

    expect((new PleskModule)->suspend($service))
        ->toMatchArray(['success' => false, 'message' => 'Object not found']);
});

it('keeps the plesk customer when other subscriptions are left', function () {
    fakePleskAgent([
        'webspace-del' => '<result><status>ok</status><id>12</id></result>',
        'webspace-get' => webspaceResult(11, 'bianchi.com', 5),
    ]);
    $service = existingSubsService(existingSubsServer(), ['plesk_domain_id' => '12', 'plesk_client_id' => '5']);

    expect((new PleskModule)->terminate($service)['success'])->toBeTrue();
    Http::assertSent(fn (Request $request) => str_contains($request->body(), '<webspace><del><filter><id>12</id>'));
    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');
});

it('removes the plesk customer with their last subscription', function () {
    fakePleskAgent([
        'webspace-del' => '<result><status>ok</status><id>12</id></result>',
        'webspace-get' => '',
    ]);
    Http::fake(['https://plesk.test:8443/api/v2/clients/5' => Http::response(null, 204)]);
    $service = existingSubsService(existingSubsServer(), ['plesk_domain_id' => '12', 'plesk_client_id' => '5']);

    expect((new PleskModule)->terminate($service)['success'])->toBeTrue();
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://plesk.test:8443/api/v2/clients/5');
});

it('does not touch the customer when plesk would not delete the subscription', function () {
    fakePleskAgent(['webspace-del' => '<result><status>error</status><errcode>1013</errcode><errtext>Object not found</errtext></result>']);
    $service = existingSubsService(existingSubsServer(), ['plesk_domain_id' => '12', 'plesk_client_id' => '5']);

    expect((new PleskModule)->terminate($service)['success'])->toBeFalse();
    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');
});
