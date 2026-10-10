<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Servers\AaPanel\AaPanelModule;

/*
 * The aaPanel server module: Sub aaPanel accounts through the panel's
 * /v2/virtual API, the same calls aaPanel's own billing integration makes.
 *
 * The answers below are shaped like the real ones. get_service_info,
 * get_account_list with no accounts ("message": null), a wrong key and the
 * unlicensed refusal were read from a real aaPanel 8.0.6 with Sub aaPanel
 * 3.0.4; the account and package fields are the ones the panel's own module
 * reads and writes.
 */

const AAPANEL_KEY = 'aapanel-test-secret';

function aapanelServer(): Server
{
    return Server::factory()->create([
        'type' => 'aapanel', 'hostname' => 'panel.example.com', 'ip_address' => '',
        'port' => 39361, 'username' => '', 'password' => '', 'access_hash' => AAPANEL_KEY,
    ]);
}

function aapanelService(Server $server, array $moduleData = []): Service
{
    $client = Client::factory()->create(['email' => 'owner@example.com']);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'aapanel',
        'config_options' => json_encode(['package_name' => 'Starter']),
    ]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'order_id' => Order::factory()->create(['client_id' => $client->id])->id,
        'domain' => 'shop.example.com', 'status' => 'pending', 'username' => 'shopuser', 'password' => 'Secret-pass-1',
    ]);
    $service->forceFill(['module_data' => $moduleData])->save();

    return $service->fresh();
}

function aapanelPackage(array $overrides = []): array
{
    return array_merge([
        'package_id' => 3, 'package_name' => 'Starter', 'disk_space_quota' => 1073741824,
        'monthly_bandwidth_limit' => 10737418240, 'max_site_limit' => 5, 'max_database' => 5,
        'php_start_children' => 1, 'php_max_children' => 5,
    ], $overrides);
}

function aapanelAccount(array $overrides = []): array
{
    return array_merge([
        'account_id' => 12, 'username' => 'shopuser', 'email' => 'owner@example.com', 'expire_date' => '0000-00-00',
        'package_id' => 3, 'package_name' => 'Starter', 'remark' => 'pnlcs:owner', 'status' => 1, 'domain' => '',
        'disk_space_quota' => 1073741824, 'disk_space_used' => 52428800, 'monthly_bandwidth_limit' => 10737418240,
        'monthly_bandwidth_used' => 1048576, 'max_site_limit' => 5, 'max_database' => 5, 'max_email_account' => 0,
        'php_start_children' => 1, 'php_max_children' => 5,
    ], $overrides);
}

function aapanelList(array $rows): array
{
    return ['status' => 0, 'msg' => '获取成功', 'message' => $rows === [] ? null : ['list' => $rows, 'page' => ['count' => count($rows)]]];
}

/**
 * A fake aaPanel: answers by action, records every request, and can fail one
 * action. $state['accounts'] is what get_account_list returns.
 */
function fakeAaPanel(array &$state): void
{
    $state += ['accounts' => [], 'packages' => [aapanelPackage()], 'fail' => [], 'requests' => []];
    Http::fake(function (Request $request) use (&$state) {
        $action = basename(parse_url($request->url(), PHP_URL_PATH), '.json');
        $state['requests'][] = [$action, $request->data()];
        if (isset($state['fail'][$action])) {
            return $state['fail'][$action]();
        }

        return Http::response(match ($action) {
            'get_service_info' => ['status' => 0, 'message' => ['install_status' => 2, 'run_status' => 1, 'version' => '3.0.4', 'server_port' => '41735']],
            'get_package_list' => aapanelList($state['packages']),
            'get_disk_list' => ['status' => 0, 'message' => ['list' => [['mountpoint' => '/www', 'is_default' => 1]]]],
            'get_account_list' => aapanelList(array_values(array_filter($state['accounts'], fn ($a) => ($a['username'] ?? '') === ($request->data()['search_value'] ?? $a['username']) || ($request->data()['search_value'] ?? '') === ''))),
            'create_account' => (function () use (&$state, $request) {
                $d = $request->data();
                $state['accounts'][] = aapanelAccount(['account_id' => 40 + count($state['accounts']), 'username' => $d['username'], 'remark' => $d['remark'], 'email' => $d['email']]);

                return ['status' => 0, 'msg' => 'ok'];
            })(),
            'modify_account' => (function () use (&$state, $request) {
                $d = $request->data();
                foreach ($state['accounts'] as &$a) {
                    if ((string) $a['account_id'] === (string) $d['account_id']) {
                        $a = array_merge($a, array_intersect_key($d, $a));
                    }
                }

                return ['status' => 0, 'msg' => 'ok'];
            })(),
            'remove_account' => (function () use (&$state, $request) {
                $state['accounts'] = array_values(array_filter($state['accounts'], fn ($a) => (string) $a['account_id'] !== (string) $request->data()['account_id']));

                return ['status' => 0, 'msg' => 'ok'];
            })(),
            'get_account_temp_login_token' => ['status' => 0, 'message' => ['token' => 'tmp-token-1', 'login_url' => 'https://user.example.com:41735/login']],
            default => ['status' => -1, 'msg' => 'unknown'],
        });
    });
}

function aapanelActions(array $state): array
{
    return array_column($state['requests'], 0);
}

test('it is a built-in server module', function () {
    expect(app(ModuleRegistry::class)->getServerModule('aapanel'))->toBeInstanceOf(AaPanelModule::class);
});

test('every request is signed the way the panel checks it', function () {
    $state = [];
    fakeAaPanel($state);
    (new AaPanelModule)->testConnection(aapanelServer());

    [$action, $data] = $state['requests'][0];
    expect($action)->toBe('get_service_info')
        ->and($data['request_time'])->toMatch('/^\d{13}$/') // milliseconds, as the panel's own module sends it
        ->and($data['request_token'])->toBe(md5($data['request_time'].md5(AAPANEL_KEY)));
    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://panel.example.com:39361/v2/virtual/'));
});

test('the connection test passes only when Sub aaPanel runs and answers', function () {
    $state = [];
    fakeAaPanel($state);
    expect((new AaPanelModule)->testConnection(aapanelServer()))->toBeTrue();

    $state = ['fail' => ['get_package_list' => fn () => Http::response(['code' => 1001, 'status' => -1, 'msg' => '授权验证失败： unauthorized'])]];
    fakeAaPanel($state);
    expect((new AaPanelModule)->testConnection(aapanelServer()))->toBeFalse();
});

test('the package list offers the panel resource packages by name', function () {
    $state = ['packages' => [aapanelPackage(), aapanelPackage(['package_id' => 4, 'package_name' => 'Business'])]];
    fakeAaPanel($state);

    expect((new AaPanelModule)->listPackages(aapanelServer()))
        ->toBe([['id' => 'Starter', 'name' => 'Starter'], ['id' => 'Business', 'name' => 'Business']]);
});

test('create opens one account with the package limits and records it', function () {
    $state = [];
    fakeAaPanel($state);
    $service = aapanelService(aapanelServer());

    $result = (new AaPanelModule)->create($service);

    expect($result['success'])->toBeTrue();
    $create = collect($state['requests'])->firstWhere(0, 'create_account')[1];
    expect($create)->toMatchArray([
        'username' => 'shopuser', 'email' => 'owner@example.com', 'package_id' => '3', 'mountpoint' => '/www',
        'disk_space_quota' => '1073741824', 'max_site_limit' => '5', 'expire_date' => '0000-00-00', 'automatic_dns' => '0',
    ]);
    $data = $service->fresh()->module_data;
    expect($data['aapanel_account_id'])->toBe(40)
        ->and($data['aapanel_owner'])->toStartWith('pnlcs:')
        ->and($data['aapanel_owner'])->toBe($create['remark'])
        ->and(array_count_values(aapanelActions($state))['create_account'])->toBe(1);
});

test('the first account on an empty panel can be created', function () {
    // The panel answers an empty account list with "message": null.
    $state = [];
    fakeAaPanel($state);

    expect((new AaPanelModule)->create(aapanelService(aapanelServer()))['success'])->toBeTrue();
});

test('create refuses a same-named account that PNLCS does not own', function () {
    $state = ['accounts' => [aapanelAccount(['remark' => 'someone else'])]];
    fakeAaPanel($state);

    $result = (new AaPanelModule)->create(aapanelService(aapanelServer()));

    expect($result['success'])->toBeFalse()
        ->and(aapanelActions($state))->not->toContain('create_account', 'modify_account', 'remove_account');
});

test('a create whose answer was lost is not sent a second time', function () {
    $state = ['fail' => ['create_account' => fn () => throw new ConnectionException('timed out')]];
    fakeAaPanel($state);
    $service = aapanelService(aapanelServer());

    expect((new AaPanelModule)->create($service)['success'])->toBeFalse();
    expect((new AaPanelModule)->create($service->fresh())['success'])->toBeFalse()
        ->and(array_count_values(aapanelActions($state))['create_account'])->toBe(1);
});

test('suspend and unsuspend change only the status', function () {
    $state = ['accounts' => [aapanelAccount()]];
    fakeAaPanel($state);
    $service = aapanelService(aapanelServer(), ['aapanel_account_id' => 12, 'aapanel_username' => 'shopuser', 'aapanel_owner' => 'pnlcs:owner']);
    $service->forceFill(['module_data' => $service->module_data + ['aapanel_server_id' => $service->server_id]])->save();

    expect((new AaPanelModule)->suspend($service->fresh())['success'])->toBeTrue()
        ->and((string) $state['accounts'][0]['status'])->toBe('0');
    $modify = collect($state['requests'])->where(0, 'modify_account')->last()[1];
    expect($modify)->toMatchArray(['account_id' => '12', 'username' => 'shopuser', 'package_id' => '3', 'status' => '0', 'automatic_dns' => '0']);

    expect((new AaPanelModule)->unsuspend($service->fresh())['success'])->toBeTrue()
        ->and((string) $state['accounts'][0]['status'])->toBe('1');
});

test('a password change reaches the panel and is kept on the service', function () {
    $state = ['accounts' => [aapanelAccount()]];
    fakeAaPanel($state);
    $server = aapanelServer();
    $service = aapanelService($server, ['aapanel_account_id' => 12, 'aapanel_username' => 'shopuser', 'aapanel_owner' => 'pnlcs:owner', 'aapanel_server_id' => $server->id]);

    expect((new AaPanelModule)->changePassword($service, 'New-pass-9876')['success'])->toBeTrue()
        ->and(collect($state['requests'])->firstWhere(0, 'modify_account')[1]['password'])->toBe('New-pass-9876')
        ->and($service->fresh()->password)->toBe('New-pass-9876');
});

test('a package change moves the account to the new package and its limits', function () {
    $state = ['accounts' => [aapanelAccount()], 'packages' => [aapanelPackage(), aapanelPackage(['package_id' => 4, 'package_name' => 'Business', 'max_site_limit' => 20])]];
    fakeAaPanel($state);
    $server = aapanelServer();
    $service = aapanelService($server, ['aapanel_account_id' => 12, 'aapanel_username' => 'shopuser', 'aapanel_owner' => 'pnlcs:owner', 'aapanel_server_id' => $server->id]);

    $result = (new AaPanelModule)->changePackage($service, ['config_options' => ['package_name' => 'Business']]);

    expect($result['success'])->toBeTrue()
        ->and(collect($state['requests'])->firstWhere(0, 'modify_account')[1])->toMatchArray(['package_id' => '4', 'max_site_limit' => '20']);
});

test('terminate removes the account once and a repeat sends nothing more', function () {
    $state = ['accounts' => [aapanelAccount()]];
    fakeAaPanel($state);
    $server = aapanelServer();
    $service = aapanelService($server, ['aapanel_account_id' => 12, 'aapanel_username' => 'shopuser', 'aapanel_owner' => 'pnlcs:owner', 'aapanel_server_id' => $server->id]);

    expect((new AaPanelModule)->terminate($service)['success'])->toBeTrue();
    expect(collect($state['requests'])->firstWhere(0, 'remove_account')[1])->toMatchArray(['account_id' => '12', 'is_del_resources' => '1'])
        ->and($state['accounts'])->toBe([]);

    expect((new AaPanelModule)->terminate($service->fresh())['success'])->toBeTrue()
        ->and(array_count_values(aapanelActions($state))['remove_account'])->toBe(1);
});

test('an unlicensed panel is reported as such and nothing is written', function () {
    $state = ['fail' => ['get_package_list' => fn () => Http::response(['code' => 1001, 'status' => -1, 'msg' => '授权验证失败： unauthorized'])]];
    fakeAaPanel($state);

    $result = (new AaPanelModule)->create(aapanelService(aapanelServer()));

    expect($result['message'])->toBe('Sub aaPanel is not licensed on this panel (aaPanel Pro is required).')
        ->and(aapanelActions($state))->not->toContain('create_account');
});

test('a wrong API key is reported in the panel own words', function () {
    $state = ['fail' => ['get_service_info' => fn () => Http::response(['status' => false, 'msg' => 'Secret key verification failed'])]];
    fakeAaPanel($state);

    expect((new AaPanelModule)->create(aapanelService(aapanelServer()))['message'])
        ->toBe('aaPanel refused the request: Secret key verification failed');
});

test('usage is read in megabytes from the account list', function () {
    $state = ['accounts' => [aapanelAccount()]];
    fakeAaPanel($state);
    $server = aapanelServer();
    $service = aapanelService($server, ['aapanel_account_id' => 12, 'aapanel_username' => 'shopuser', 'aapanel_owner' => 'pnlcs:owner', 'aapanel_server_id' => $server->id]);
    $service->forceFill(['status' => 'active'])->save();

    expect((new AaPanelModule)->usageUpdate($server))->toBe(['updated' => 1, 'errors' => 0]);
    $fresh = $service->fresh();
    expect((int) $fresh->disk_usage)->toBe(50)->and((int) $fresh->disk_limit)->toBe(1024)
        ->and((int) $fresh->bw_usage)->toBe(1)->and((int) $fresh->bw_limit)->toBe(10240);
});

test('the customer signs in to their own Sub aaPanel with a single-use link', function () {
    $state = ['accounts' => [aapanelAccount()]];
    fakeAaPanel($state);
    $server = aapanelServer();
    $service = aapanelService($server, ['aapanel_account_id' => 12, 'aapanel_username' => 'shopuser', 'aapanel_owner' => 'pnlcs:owner', 'aapanel_server_id' => $server->id]);
    $service->forceFill(['status' => 'active'])->save();

    $result = (new AaPanelModule)->ssoLogin($service->fresh());

    expect($result['data']['url'])->toBe('https://user.example.com:41735/login?token=tmp-token-1')
        ->and((string) collect($state['requests'])->firstWhere(0, 'get_account_temp_login_token')[1]['account_id'])->toBe('12');
});

test('the service page offers Login to panel and the button lands in Sub aaPanel', function () {
    $state = ['accounts' => [aapanelAccount()]];
    fakeAaPanel($state);
    $server = aapanelServer();
    $service = aapanelService($server, ['aapanel_account_id' => 12, 'aapanel_username' => 'shopuser', 'aapanel_owner' => 'pnlcs:owner', 'aapanel_server_id' => $server->id]);
    $service->forceFill(['status' => 'active'])->save();
    $user = User::factory()->create();
    $user->clients()->attach($service->client_id);

    $this->actingAs($user)->get(route('client.services.show', $service))
        ->assertOk()->assertSee(route('client.services.login', $service), false);
    $this->actingAs($user)->get(route('client.services.login', $service))
        ->assertRedirect('https://user.example.com:41735/login?token=tmp-token-1');
});
