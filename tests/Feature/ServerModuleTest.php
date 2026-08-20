<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\Order;
use App\Services\Module\ModuleRegistry;
use App\Services\ProvisioningService;
use Illuminate\Support\Facades\Http;

// ============================================================================
// CUSTOM MODULE (baseline sanity)
// ============================================================================

test('custom module is registered and implements interface', function () {
    $registry = app(ModuleRegistry::class);
    $module   = $registry->getServerModule('custom');
    expect($module)->not->toBeNull();
    expect($module)->toBeInstanceOf(\App\Contracts\ServerModuleInterface::class);
});

// ============================================================================
// PLESK MODULE
// ============================================================================

test('plesk module returns correct name', function () {
    $module = new \Modules\Servers\Plesk\PleskModule();
    expect($module->getModuleName())->toBe('plesk');
});

test('plesk module has config fields with api_key', function () {
    $module = new \Modules\Servers\Plesk\PleskModule();
    $fields = $module->getConfigFields();
    expect($fields)->toBeArray()->not->toBeEmpty();
    $names = array_column($fields, 'name');
    expect($names)->toContain('api_key');
});

test('plesk module is registered in registry', function () {
    $registry = app(ModuleRegistry::class);
    $module   = $registry->getServerModule('plesk');
    expect($module)->not->toBeNull();
    expect($module)->toBeInstanceOf(\Modules\Servers\Plesk\PleskModule::class);
});

test('plesk test connection returns true with mocked API', function () {
    Http::fake([
        '*/api/v2/server' => Http::response(['platform' => ['os' => 'Linux'], 'version' => '18.0.60'], 200),
    ]);

    $server = Server::factory()->create([
        'type'        => 'plesk',
        'hostname'    => 'plesk.test',
        'port'        => 8443,
        'access_hash' => 'secret-key-123',
    ]);

    $module = new \Modules\Servers\Plesk\PleskModule();
    $result = $module->testConnection($server);
    expect($result)->toBeTrue();
});

test('plesk test connection returns false on API error', function () {
    Http::fake([
        '*/api/v2/server' => Http::response('Unauthorized', 401),
    ]);

    $server = Server::factory()->create([
        'type'        => 'plesk',
        'hostname'    => 'plesk.test',
        'port'        => 8443,
        'access_hash' => 'bad-key',
    ]);

    $module = new \Modules\Servers\Plesk\PleskModule();
    $result = $module->testConnection($server);
    expect($result)->toBeFalse();
});

test('plesk create account succeeds with mocked API', function () {
    Http::fake([
        '*/api/v2/clients'   => Http::response(['id' => 'client-uuid-111', 'login' => 'testuser'], 201),
        '*/api/v2/domains'   => Http::response(['id' => 'dom-uuid-222', 'name' => 'test.com'], 201),
    ]);

    $server  = Server::factory()->create(['type' => 'plesk', 'hostname' => 'plesk.test', 'port' => 8443, 'access_hash' => 'sk']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id'       => $group->id,
        'server_type'    => 'plesk',
        'config_options' => json_encode(['package_name' => 'Basic']),
    ]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'domain'     => 'test.com',
        'status'     => 'pending',
        'username'   => 'testuser',
    ]);

    $module = new \Modules\Servers\Plesk\PleskModule();
    $result = $module->create($service);

    expect($result['success'])->toBeTrue();
    expect($result['data']['plesk_client_id'])->toBe('client-uuid-111');
    expect($result['data']['plesk_domain_id'])->toBe('dom-uuid-222');
});

test('plesk create account rolls back client on webspace failure', function () {
    Http::fake([
        '*/api/v2/clients'    => Http::response(['id' => 'client-uuid-rollback'], 201),
        '*/api/v2/clients/*'  => Http::response(null, 200),
        '*/api/v2/domains'    => Http::response('Internal Server Error', 500),
    ]);

    $server  = Server::factory()->create(['type' => 'plesk', 'hostname' => 'plesk.test', 'port' => 8443, 'access_hash' => 'sk']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'plesk']);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'domain'     => 'fail.com',
        'username'   => 'failuser',
    ]);

    $module = new \Modules\Servers\Plesk\PleskModule();
    $result = $module->create($service);

    expect($result['success'])->toBeFalse();
    // Rollback DELETE should have been called
    Http::assertSent(fn ($req) => str_contains($req->url(), 'clients/client-uuid-rollback') && $req->method() === 'DELETE');
});

test('plesk suspend account with mocked API', function () {
    Http::fake([
        '*/api/v2/clients/*' => Http::response(['status' => 16], 200),
    ]);

    $server  = Server::factory()->create(['type' => 'plesk', 'hostname' => 'plesk.test', 'access_hash' => 'sk']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'notes'      => json_encode(['plesk_client_id' => 'client-555', 'plesk_webspace_id' => 'ws-555']),
        'status'     => 'active',
    ]);

    $module = new \Modules\Servers\Plesk\PleskModule();
    $result = $module->suspend($service, 'Non-payment');
    expect($result['success'])->toBeTrue();
});

test('plesk unsuspend account with mocked API', function () {
    Http::fake([
        '*/api/v2/clients/*' => Http::response(['status' => 0], 200),
    ]);

    $server  = Server::factory()->create(['type' => 'plesk', 'hostname' => 'plesk.test', 'access_hash' => 'sk']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'notes'      => json_encode(['plesk_client_id' => 'client-555']),
    ]);

    $module = new \Modules\Servers\Plesk\PleskModule();
    $result = $module->unsuspend($service);
    expect($result['success'])->toBeTrue();
});

test('plesk terminate account with mocked API', function () {
    Http::fake([
        '*/api/v2/clients/*' => Http::response(null, 200),
    ]);

    $server  = Server::factory()->create(['type' => 'plesk', 'hostname' => 'plesk.test', 'access_hash' => 'sk']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'notes'      => json_encode(['plesk_client_id' => 'client-777']),
    ]);

    $module = new \Modules\Servers\Plesk\PleskModule();
    $result = $module->terminate($service);
    expect($result['success'])->toBeTrue();
});

test('plesk returns failure when no server assigned', function () {
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'plesk']);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => null,
        'order_id'   => $order->id,
    ]);

    $module = new \Modules\Servers\Plesk\PleskModule();
    $result = $module->create($service);
    expect($result['success'])->toBeFalse();
});

// ============================================================================
// DIRECTADMIN MODULE
// ============================================================================

test('directadmin module returns correct name', function () {
    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    expect($module->getModuleName())->toBe('directadmin');
});

test('directadmin module has config fields with login_key', function () {
    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $fields = $module->getConfigFields();
    expect($fields)->toBeArray()->not->toBeEmpty();
    $names = array_column($fields, 'name');
    expect($names)->toContain('login_key');
});

test('directadmin module is registered in registry', function () {
    $registry = app(ModuleRegistry::class);
    $module   = $registry->getServerModule('directadmin');
    expect($module)->not->toBeNull();
    expect($module)->toBeInstanceOf(\Modules\Servers\DirectAdmin\DirectAdminModule::class);
});

test('directadmin test connection returns true with mocked API', function () {
    Http::fake([
        '*/CMD_API_SHOW_ALL_USERS*' => Http::response('list[]=admin&list[]=testuser', 200),
    ]);

    $server = Server::factory()->create([
        'type'     => 'directadmin',
        'hostname' => 'da.test',
        'port'     => 2222,
        'username' => 'admin',
        'password' => 'pass123',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->testConnection($server);
    expect($result)->toBeTrue();
});

test('directadmin test connection returns false on API error', function () {
    Http::fake([
        '*/CMD_API_SHOW_ALL_USERS*' => Http::response('error=1&text=Login+Invalid', 401),
    ]);

    $server = Server::factory()->create([
        'type'     => 'directadmin',
        'hostname' => 'da.test',
        'port'     => 2222,
        'username' => 'admin',
        'password' => 'wrong',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->testConnection($server);
    expect($result)->toBeFalse();
});

test('directadmin create account succeeds with mocked API', function () {
    Http::fake([
        '*/CMD_API_ACCOUNT_USER' => Http::response('error=0&text=Account+Created', 200),
    ]);

    $server  = Server::factory()->create(['type' => 'directadmin', 'hostname' => 'da.test', 'port' => 2222, 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id'       => $group->id,
        'server_type'    => 'directadmin',
        'config_options' => json_encode(['package_name' => 'Basic']),
    ]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'domain'     => 'example.com',
        'username'   => 'dauser',
        'status'     => 'pending',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->create($service);
    expect($result['success'])->toBeTrue();
});

test('directadmin create account fails on API error', function () {
    Http::fake([
        '*/CMD_API_ACCOUNT_USER' => Http::response('error=1&text=Username+already+exists', 200),
    ]);

    $server  = Server::factory()->create(['type' => 'directadmin', 'hostname' => 'da.test', 'port' => 2222, 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'directadmin']);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'username'   => 'dupeuser',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->create($service);
    expect($result['success'])->toBeFalse();
});

test('directadmin suspend account with mocked API', function () {
    Http::fake([
        '*/CMD_API_SELECT_USERS' => Http::response('error=0&text=Users+suspended', 200),
    ]);

    $server  = Server::factory()->create(['type' => 'directadmin', 'hostname' => 'da.test', 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'username'   => 'dauser',
        'status'     => 'active',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->suspend($service, 'Non-payment');
    expect($result['success'])->toBeTrue();
});

test('directadmin unsuspend account with mocked API', function () {
    Http::fake([
        '*/CMD_API_SELECT_USERS' => Http::response('error=0&text=Users+unsuspended', 200),
    ]);

    $server  = Server::factory()->create(['type' => 'directadmin', 'hostname' => 'da.test', 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'username'   => 'dauser',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->unsuspend($service);
    expect($result['success'])->toBeTrue();
});

test('directadmin terminate account with mocked API', function () {
    Http::fake([
        '*/CMD_API_SELECT_USERS' => Http::response('error=0&text=Users+deleted', 200),
    ]);

    $server  = Server::factory()->create(['type' => 'directadmin', 'hostname' => 'da.test', 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'username'   => 'dauser',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->terminate($service);
    expect($result['success'])->toBeTrue();
});

test('directadmin change password with mocked API', function () {
    Http::fake([
        '*/CMD_API_USER_PASSWD' => Http::response('error=0&text=Password+changed', 200),
    ]);

    $server  = Server::factory()->create(['type' => 'directadmin', 'hostname' => 'da.test', 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'username'   => 'dauser',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->changePassword($service, 'NewPass123!');
    expect($result['success'])->toBeTrue();
});

test('directadmin change package with mocked API', function () {
    Http::fake([
        '*/CMD_API_MODIFY_USER' => Http::response('error=0&text=User+modified', 200),
    ]);

    $server  = Server::factory()->create(['type' => 'directadmin', 'hostname' => 'da.test', 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'username'   => 'dauser',
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->changePackage($service, ['package_name' => 'Premium']);
    expect($result['success'])->toBeTrue();
});

test('directadmin returns failure when no server assigned', function () {
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'directadmin']);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => null,
        'order_id'   => $order->id,
    ]);

    $module = new \Modules\Servers\DirectAdmin\DirectAdminModule();
    $result = $module->create($service);
    expect($result['success'])->toBeFalse();
});

// ============================================================================
// PANELICA MODULE (conditional on class existence)
// ============================================================================

test('panelica module returns correct name', function () {
    if (!class_exists(\Modules\Servers\Panelica\PanelicaModule::class)) {
        $this->markTestSkipped('PanelicaModule not yet available.');
    }
    $module = new \Modules\Servers\Panelica\PanelicaModule();
    expect($module->getModuleName())->toBe('panelica');
});

test('panelica module asks for no extra config fields', function () {
    if (!class_exists(\Modules\Servers\Panelica\PanelicaModule::class)) {
        $this->markTestSkipped('PanelicaModule not yet available.');
    }
    $module = new \Modules\Servers\Panelica\PanelicaModule();

    // Unlike cPanel or Plesk, this module carries its credentials in the
    // standard server fields - port 8443, the API key in Password and the API
    // secret in Access Hash - so it deliberately declares none of its own.
    // Adding one here without adding it to the server form would show the
    // operator a field that is never saved.
    expect($module->getConfigFields())->toBeArray()->toBeEmpty();
});

test('panelica test connection with mocked API', function () {
    if (!class_exists(\Modules\Servers\Panelica\PanelicaModule::class)) {
        $this->markTestSkipped('PanelicaModule not yet available.');
    }

    Http::fake([
        '*/v1/server/status' => Http::response(['status' => 'success', 'data' => ['status' => 'online']], 200),
    ]);

    $server = Server::factory()->create([
        'type'        => 'panelica',
        'hostname'    => 'test.local',
        'port'        => 8443,
        'password'    => 'pk_test_key',
        'access_hash' => 'sk_test_secret',
    ]);

    $module = new \Modules\Servers\Panelica\PanelicaModule();
    $result = $module->testConnection($server);
    expect($result)->toBeTrue();
});

test('panelica create account with mocked API', function () {
    if (!class_exists(\Modules\Servers\Panelica\PanelicaModule::class)) {
        $this->markTestSkipped('PanelicaModule not yet available.');
    }

    Http::fake([
        '*/v1/accounts' => Http::response(['status' => 'success', 'data' => ['id' => 'user-uuid-123', 'username' => 'testuser']], 201),
        '*/v1/domains'  => Http::response(['status' => 'success', 'data' => ['id' => 'domain-uuid-456']], 201),
    ]);

    $server  = Server::factory()->create(['type' => 'panelica', 'hostname' => 'test.local', 'port' => 8443, 'password' => 'pk_test', 'access_hash' => 'sk_test']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id'       => $group->id,
        'server_type'    => 'panelica',
        'config_options' => json_encode(['panelica_plan_id' => 'plan-uuid-789']),
    ]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'domain'     => 'test.com',
        'status'     => 'pending',
    ]);

    $module = new \Modules\Servers\Panelica\PanelicaModule();
    $result = $module->create($service);
    expect($result['success'])->toBeTrue();
});

test('panelica suspend with mocked API', function () {
    if (!class_exists(\Modules\Servers\Panelica\PanelicaModule::class)) {
        $this->markTestSkipped('PanelicaModule not yet available.');
    }

    Http::fake([
        '*/v1/accounts/*/suspend' => Http::response(['status' => 'success'], 200),
    ]);

    $server  = Server::factory()->create(['type' => 'panelica', 'password' => 'pk', 'access_hash' => 'sk']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'notes'      => json_encode(['panelica_user_id' => 'uuid-123']),
        'status'     => 'active',
    ]);

    $module = new \Modules\Servers\Panelica\PanelicaModule();
    $result = $module->suspend($service, 'Non-payment');
    expect($result['success'])->toBeTrue();
});

// ============================================================================
// CPANEL MODULE (conditional on class existence)
// ============================================================================

test('cpanel module returns correct name', function () {
    if (!class_exists(\Modules\Servers\CPanel\CPanelModule::class)) {
        $this->markTestSkipped('CPanelModule not yet available.');
    }
    $module = new \Modules\Servers\CPanel\CPanelModule();
    expect($module->getModuleName())->toBe('cpanel');
});

test('cpanel module has config fields', function () {
    if (!class_exists(\Modules\Servers\CPanel\CPanelModule::class)) {
        $this->markTestSkipped('CPanelModule not yet available.');
    }
    $module = new \Modules\Servers\CPanel\CPanelModule();
    $fields = $module->getConfigFields();
    expect($fields)->toBeArray();
});

test('cpanel test connection with mocked API', function () {
    if (!class_exists(\Modules\Servers\CPanel\CPanelModule::class)) {
        $this->markTestSkipped('CPanelModule not yet available.');
    }

    Http::fake([
        '*/json-api/version*' => Http::response(['metadata' => ['result' => 1], 'data' => ['version' => '118.0.6']], 200),
    ]);

    $server = Server::factory()->create([
        'type'        => 'cpanel',
        'hostname'    => 'cpanel.test',
        'port'        => 2087,
        'username'    => 'root',
        'access_hash' => 'test-token',
    ]);

    $module = new \Modules\Servers\CPanel\CPanelModule();
    $result = $module->testConnection($server);
    expect($result)->toBeTrue();
});

test('cpanel create account with mocked API', function () {
    if (!class_exists(\Modules\Servers\CPanel\CPanelModule::class)) {
        $this->markTestSkipped('CPanelModule not yet available.');
    }

    Http::fake([
        '*/json-api/createacct*' => Http::response(['metadata' => ['result' => 1], 'data' => ['account' => 'testuser']], 200),
    ]);

    $server  = Server::factory()->create(['type' => 'cpanel', 'hostname' => 'cpanel.test', 'port' => 2087, 'username' => 'root', 'access_hash' => 'token']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id'       => $group->id,
        'server_type'    => 'cpanel',
        'config_options' => json_encode(['cpanel_package' => 'Basic']),
    ]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'domain'     => 'example.com',
        'status'     => 'pending',
    ]);

    $module = new \Modules\Servers\CPanel\CPanelModule();
    $result = $module->create($service);
    expect($result['success'])->toBeTrue();
});

// ============================================================================
// MODULE REGISTRY — all modules
// ============================================================================

test('plesk and directadmin modules are always registered', function () {
    $registry = app(ModuleRegistry::class);
    expect($registry->getServerModule('plesk'))->not->toBeNull();
    expect($registry->getServerModule('directadmin'))->not->toBeNull();
});

test('panelica and cpanel modules are registered when classes exist', function () {
    $registry = app(ModuleRegistry::class);

    if (class_exists(\Modules\Servers\Panelica\PanelicaModule::class)) {
        expect($registry->getServerModule('panelica'))->not->toBeNull();
    }

    if (class_exists(\Modules\Servers\CPanel\CPanelModule::class)) {
        expect($registry->getServerModule('cpanel'))->not->toBeNull();
    }

    // If neither exists, just assert the registry itself is alive
    expect($registry)->not->toBeNull();
});

test('nonexistent module returns null from registry', function () {
    $registry = app(ModuleRegistry::class);
    expect($registry->getServerModule('nonexistent_panel_xyz'))->toBeNull();
});

// ============================================================================
// PROVISIONING SERVICE — module dispatch
// ============================================================================

test('provisioning service dispatches to plesk module', function () {
    $server = Server::factory()->create(['type' => 'plesk', 'access_hash' => 'sk']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'plesk']);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
    ]);

    $provisioning = app(ProvisioningService::class);
    $module = $provisioning->resolveModule($service->fresh(['product']));
    expect($module)->not->toBeNull();
    expect($module->getModuleName())->toBe('plesk');
});

test('provisioning service dispatches to directadmin module', function () {
    $server  = Server::factory()->create(['type' => 'directadmin', 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'directadmin']);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
    ]);

    $provisioning = app(ProvisioningService::class);
    $module = $provisioning->resolveModule($service->fresh(['product']));
    expect($module)->not->toBeNull();
    expect($module->getModuleName())->toBe('directadmin');
});

test('provisioning service returns null for product without server_type', function () {
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => null]);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'order_id'   => $order->id,
    ]);

    $provisioning = app(ProvisioningService::class);
    $module = $provisioning->resolveModule($service->fresh(['product']));
    expect($module)->toBeNull();
});

test('provisioning createAccount with plesk succeeds end-to-end', function () {
    Http::fake([
        '*/api/v2/clients'   => Http::response(['id' => 'c-e2e-111'], 201),
        '*/api/v2/domains'   => Http::response(['id' => 'dom-e2e-222'], 201),
    ]);

    $server  = Server::factory()->create(['type' => 'plesk', 'hostname' => 'plesk.test', 'port' => 8443, 'access_hash' => 'key']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'plesk']);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'domain'     => 'e2e.com',
        'username'   => 'e2euser',
        'status'     => 'pending',
    ]);

    $provisioning = app(ProvisioningService::class);
    $result = $provisioning->createAccount($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeTrue();
    expect($service->fresh()->status)->toBe('active');
});

test('provisioning createAccount with directadmin succeeds end-to-end', function () {
    Http::fake([
        '*/CMD_API_ACCOUNT_USER' => Http::response('error=0&text=Account+Created', 200),
    ]);

    $server  = Server::factory()->create(['type' => 'directadmin', 'hostname' => 'da.test', 'port' => 2222, 'username' => 'admin', 'password' => 'pass']);
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id, 'server_type' => 'directadmin']);
    $client  = Client::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id'  => $client->id,
        'product_id' => $product->id,
        'server_id'  => $server->id,
        'order_id'   => $order->id,
        'domain'     => 'datest.com',
        'username'   => 'dauser',
        'status'     => 'pending',
    ]);

    $provisioning = app(ProvisioningService::class);
    $result = $provisioning->createAccount($service->fresh(['product', 'server', 'client']));

    expect($result['success'])->toBeTrue();
    expect($service->fresh()->status)->toBe('active');
});
