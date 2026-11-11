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
 * Selling Plesk reseller accounts. A product whose package is a reseller plan
 * creates a reseller instead of a customer and subscription, and every action
 * on the service then addresses that reseller.
 */

/**
 * A Plesk agent answering each object and verb from $replies (e.g.
 * "reseller-add" => inner XML); unlisted operations answer ok.
 *
 * @param  array<string, string>  $replies
 */
function fakeResellerPlesk(array $replies = []): void
{
    $replies += [
        'reseller-plan-get' => '<result><status>ok</status><id>1</id><name>Reseller 25</name><guid>guid-25</guid></result>'
            .'<result><status>ok</status><id>2</id><name>Reseller 50</name><guid>guid-50</guid></result>',
        'service-plan-get' => '<result><status>ok</status><id>3</id><name>Hosting Pro</name><guid>guid-pro</guid></result>',
    ];

    Http::preventStrayRequests();
    Http::fake(['https://plesk.test:8443/enterprise/control/agent.php' => function (Request $request) use ($replies) {
        $body = $request->body();
        $out = '';
        foreach (['reseller-plan', 'service-plan', 'webspace', 'customer', 'reseller'] as $object) {
            foreach (['get', 'add', 'set', 'del', 'switch-subscription'] as $verb) {
                if (str_contains($body, "<{$object}><{$verb}>")) {
                    $out .= "<{$object}><{$verb}>".($replies["{$object}-{$verb}"] ?? '<result><status>ok</status></result>')."</{$verb}></{$object}>";
                }
            }
        }

        return Http::response('<?xml version="1.0"?><packet>'.$out.'</packet>', 200);
    }]);
}

function resellerServer(): Server
{
    return Server::factory()->create([
        'type' => 'plesk', 'hostname' => 'plesk.test', 'ip_address' => '', 'port' => 8443,
        'username' => 'admin', 'password' => 'admin-secret', 'access_hash' => 'sk-123',
    ]);
}

function resellerService(Server $server, string $package = 'reseller:Reseller 25', array $moduleData = []): Service
{
    $service = Service::factory()->create([
        'client_id' => Client::factory()->create([
            'company_name' => 'Web & Co Srl', 'first_name' => 'Anna', 'last_name' => 'Neri', 'email' => 'anna@webco.it',
        ])->id,
        'product_id' => Product::factory()->create([
            'group_id' => ProductGroup::factory()->create()->id,
            'server_type' => 'plesk',
            'config_options' => ['package_name' => $package],
        ])->id,
        'server_id' => $server->id,
        'domain' => null,
        'status' => 'active',
    ]);
    if ($moduleData !== []) {
        $service->forceFill(['module_data' => $moduleData])->save();
    }

    return $service->fresh();
}

/**
 * The XML packets sent to the agent.
 *
 * @return list<string>
 */
function sentPackets(): array
{
    return Http::recorded()->map(fn (array $pair) => $pair[0]->body())->values()->all();
}

it('offers the reseller plans next to the service plans', function () {
    fakeResellerPlesk();

    expect(app(PleskModule::class)->listPackages(resellerServer()))->toBe([
        ['id' => 'Hosting Pro', 'name' => 'Hosting Pro'],
        ['id' => 'reseller:Reseller 25', 'name' => 'Reseller: Reseller 25'],
        ['id' => 'reseller:Reseller 50', 'name' => 'Reseller: Reseller 50'],
    ]);
});

it('creates a reseller on the plan the product names', function () {
    fakeResellerPlesk(['reseller-add' => '<result><status>ok</status><id>41</id><guid>g</guid></result>']);
    $service = resellerService(resellerServer());

    $result = app(PleskModule::class)->create($service);

    $packet = collect(sentPackets())->first(fn ($body) => str_contains($body, '<reseller><add>'));
    expect($result['success'])->toBeTrue()
        ->and($packet)->toContain('<cname>Web &amp; Co Srl</cname>', '<pname>Anna Neri</pname>', '<email>anna@webco.it</email>', '<plan-name>Reseller 25</plan-name>')
        ->and($service->fresh()->module_data)->toMatchArray(['plesk_client_id' => '41', 'plesk_account_type' => 'reseller'])
        ->and($service->fresh()->username)->toStartWith('webco')
        ->and($service->fresh()->password)->not->toBeEmpty();
});

it('reports a reseller Plesk refused to create', function () {
    fakeResellerPlesk(['reseller-add' => '<result><status>error</status><errcode>1007</errcode><errtext>Login already exists</errtext></result>']);
    $service = resellerService(resellerServer());

    $result = app(PleskModule::class)->create($service);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Login already exists')
        ->and($service->fresh()->module_data)->toBeEmpty();
});

it('suspends, reactivates and removes the reseller, not a subscription', function () {
    fakeResellerPlesk();
    $service = resellerService(resellerServer(), moduleData: ['plesk_client_id' => '41', 'plesk_account_type' => 'reseller']);
    $module = app(PleskModule::class);

    expect($module->suspend($service)['success'])->toBeTrue()
        ->and($module->unsuspend($service)['success'])->toBeTrue()
        ->and($module->terminate($service)['success'])->toBeTrue();

    [$suspend, $unsuspend, $delete] = sentPackets();
    expect($suspend)->toContain('<reseller><set><filter><id>41</id></filter>', '<status>16</status>')
        ->and($unsuspend)->toContain('<reseller><set><filter><id>41</id></filter>', '<status>0</status>')
        ->and($delete)->toContain('<reseller><del><filter><id>41</id></filter>')
        ->and(implode('', sentPackets()))->not->toContain('<webspace>');
});

it('changes the reseller password', function () {
    fakeResellerPlesk();
    $service = resellerService(resellerServer(), moduleData: ['plesk_client_id' => '41', 'plesk_account_type' => 'reseller']);

    $result = app(PleskModule::class)->changePassword($service, 'N3w<Pass');

    expect($result['success'])->toBeTrue()
        ->and(sentPackets()[0])->toContain('<passwd>N3w&lt;Pass</passwd>')
        ->and($service->fresh()->password)->toBe('N3w<Pass');
});

it('moves a reseller to another reseller plan and nowhere else', function () {
    fakeResellerPlesk();
    $service = resellerService(resellerServer(), moduleData: ['plesk_client_id' => '41', 'plesk_account_type' => 'reseller']);
    $module = app(PleskModule::class);

    $moved = $module->changePackage($service, ['config_options' => ['package_name' => 'reseller:Reseller 50']]);
    $refused = $module->changePackage($service, ['config_options' => ['package_name' => 'Hosting Pro']]);

    expect($moved['success'])->toBeTrue()
        ->and(collect(sentPackets())->first(fn ($body) => str_contains($body, 'switch-subscription')))
        ->toContain('<filter><id>41</id></filter><plan-guid>guid-50</plan-guid>')
        ->and($refused['success'])->toBeFalse();
});

it('reads subscription owners that are resellers', function () {
    fakeResellerPlesk([
        'webspace-get' => '<result><status>ok</status><id>11</id><data><gen_info><name>rossi.it</name><owner-id>41</owner-id><status>0</status></gen_info></data></result>',
        'customer-get' => '',
        'reseller-get' => '<result><status>ok</status><id>41</id><data><gen-info><login>webco</login><cname>Web Co</cname><pname/><email>anna@webco.it</email><status>0</status></gen-info></data></result>',
    ]);

    $accounts = app(PleskModule::class)->listAccounts(resellerServer());

    expect($accounts[0])->toMatchArray(['username' => 'rossi.it · Web Co', 'email' => 'anna@webco.it'])
        ->and(implode('', sentPackets()))->toContain('<reseller><get><filter><all/></filter><dataset><gen-info/>')
        ->not->toContain('<reseller><get><filter/><dataset><gen_info/>');
});

it('lists existing resellers on the sync page and imports one as a linked service', function () {
    fakeResellerPlesk([
        'webspace-get' => '',
        'customer-get' => '',
        'reseller-get' => '<result><status>ok</status><id>41</id><data><gen-info><cr-date>2018-05-02</cr-date><login>webco</login><cname>Web Co</cname><pname/><email>anna@webco.it</email><status>0</status></gen-info>'
            .'<subscriptions><subscription><plan><plan-guid>guid-25</plan-guid></plan></subscription></subscriptions></data></result>',
    ]);
    $server = resellerServer();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'plesk',
        'config_options' => ['package_name' => 'reseller:Reseller 25'],
    ]);
    $client = Client::factory()->create(['email' => 'anna@webco.it']);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $row = $this->actingAs($admin, 'admin')->get(route('admin.config.servers.sync', $server))
        ->assertOk()
        ->assertSee(__('admin.server_sync.kind_reseller'))
        ->viewData('rows')[0];

    expect($row)->toMatchArray(['id' => 'reseller:41', 'kind' => 'reseller', 'plan' => 'Reseller 25', 'client_id' => $client->id, 'product_id' => $product->id]);

    $this->post(route('admin.config.servers.sync.import', $server), ['rows' => [
        'reseller:41' => ['client_id' => $client->id, 'product_id' => $product->id, 'billing_cycle' => 'Monthly', 'amount' => '29', 'next_due_date' => '2026-12-01'],
    ]])->assertSessionHas('success');

    $service = Service::where('server_id', $server->id)->sole();
    expect($service->domain)->toBeNull()
        ->and($service->username)->toBe('webco')
        ->and($service->module_data)->toMatchArray(['plesk_client_id' => '41', 'plesk_account_type' => 'reseller'])
        ->and(app(PleskModule::class)->linkedPanelAccount($service))->toBe('reseller:41');
});
