<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\SslOrder;
use App\Services\SslProvisioningService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * SSL products, the services that carry them, and certificates bought before
 * the panel existed.
 *
 * The Add product form had no SSL module field, so an SSL product was created
 * without the module that issues its certificate. A service moved to a product
 * of another module kept its old server and went on being driven by the old
 * module. And certificates already on the provider account could not be
 * brought in at all.
 */
function sslsmAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function sslsmProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'group_id' => ProductGroup::factory()->create()->id,
        'pay_type' => 'recurring',
        'server_type' => '',
    ], $attributes));
}

function sslsmService(Product $product, array $attributes = []): Service
{
    return Service::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'status' => 'pending',
        'domain' => 'shop.example.com',
        'amount' => 10,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth()->toDateString(),
    ], $attributes));
}

function sslsmEdit(Service $service, Product $to): array
{
    return [
        'product_id' => $to->id, 'domain' => $service->domain, 'username' => $service->username,
        'billing_cycle' => $service->billing_cycle, 'amount' => $service->amount,
        'override_auto_suspend_date' => null, 'notes' => $service->notes,
    ];
}

function fakeGoGetSsl(array|string $orders, int $status = 200): void
{
    Http::fake([
        'my.gogetssl.com/api/auth/*' => Http::response(['key' => 'test-key'], 200),
        'my.gogetssl.com/api/orders/status/*' => Http::response([
            'status' => 'active', 'crt_code' => '-----BEGIN CERTIFICATE-----X', 'ca_code' => '-----BEGIN CERTIFICATE-----CA', 'valid_till' => '2027-03-01',
        ], 200),
        'my.gogetssl.com/api/orders/*' => Http::response($orders, $status),
    ]);
}

test('an SSL product is created with the module that issues its certificate', function () {
    $this->actingAs(sslsmAdmin(), 'admin')
        ->post(route('admin.products.store'), [
            'name' => 'PositiveSSL',
            'group_id' => ProductGroup::factory()->create()->id,
            'type' => 'ssl',
            'pay_type' => 'recurring',
            'ssl_module' => 'gogetssl',
        ])->assertSessionHasNoErrors();

    expect(Product::where('name', 'PositiveSSL')->firstOrFail()->ssl_module)->toBe('gogetssl');
});

test('a service with no account moves to another module and lets go of the old server', function () {
    $server = Server::factory()->create(['type' => 'cpanel']);
    $service = sslsmService(sslsmProduct(['server_type' => 'cpanel']), ['server_id' => $server->id, 'status' => 'pending']);
    $target = sslsmProduct(['server_type' => 'directadmin']);

    $this->actingAs(sslsmAdmin(), 'admin')
        ->put(route('admin.services.update', $service), sslsmEdit($service, $target))
        ->assertSessionHas('success');

    $service->refresh();
    expect($service->product_id)->toBe($target->id)
        ->and($service->server_id)->toBeNull()
        ->and($service->status->value ?? $service->status)->toBe('pending');
});

test('a service whose account still runs is not moved to another module', function () {
    $server = Server::factory()->create(['type' => 'cpanel']);
    $product = sslsmProduct(['server_type' => 'cpanel']);
    $service = sslsmService($product, ['server_id' => $server->id, 'status' => 'active']);

    $this->actingAs(sslsmAdmin(), 'admin')
        ->put(route('admin.services.update', $service), sslsmEdit($service, sslsmProduct(['type' => 'ssl', 'ssl_module' => 'gogetssl'])))
        ->assertSessionHas('error', __('admin.services.module_change_needs_termination'));

    $service->refresh();
    expect($service->product_id)->toBe($product->id)
        ->and($service->server_id)->toBe($server->id)
        ->and($service->status->value ?? $service->status)->toBe('active');
});

test('certificates on the provider account are attached to the service with their domain', function () {
    Mail::fake();
    $hosting = sslsmService(sslsmProduct(['server_type' => 'cpanel']), ['status' => 'active']);
    $ssl = sslsmService(sslsmProduct(['type' => 'ssl', 'ssl_module' => 'gogetssl']), ['status' => 'active', 'client_id' => $hosting->client_id]);
    fakeGoGetSsl([
        ['order_id' => '9001', 'common_name' => 'Shop.Example.com', 'status' => 'active', 'valid_till' => '2027-03-01'],
        ['order_id' => '9002', 'common_name' => 'nobody.example.net', 'status' => 'active', 'valid_till' => '2027-03-01'],
    ]);

    $result = app(SslProvisioningService::class)->importRemoteOrders('gogetssl');

    expect($result)->toBe(['imported' => 1, 'updated' => 0, 'skipped' => 1, 'failed' => false]);
    $order = SslOrder::where('remote_id', '9001')->firstOrFail();
    expect($order->service_id)->toBe($ssl->id)
        ->and($order->client_id)->toBe($ssl->client_id)
        ->and($order->status)->toBe('Completed')
        ->and($order->crt_expires->toDateString())->toBe('2027-03-01')
        // The certificate itself, so it can be downloaded, renewed and revoked here.
        ->and($order->cert)->toBe('-----BEGIN CERTIFICATE-----X')
        ->and($order->isCompleted())->toBeTrue();
    Mail::assertNothingSent();
});

test('an order the panel already has keeps its service and client', function () {
    $owner = sslsmService(sslsmProduct(['type' => 'ssl', 'ssl_module' => 'gogetssl']), ['status' => 'active', 'domain' => 'other.example.org']);
    $order = SslOrder::create([
        'remote_id' => '9001', 'module' => 'gogetssl', 'domain' => 'shop.example.com', 'status' => 'Awaiting Issuance',
        'service_id' => $owner->id, 'client_id' => $owner->client_id,
    ]);
    // Another client's service that happens to carry the same domain.
    sslsmService(sslsmProduct(['server_type' => 'cpanel']), ['status' => 'active']);
    fakeGoGetSsl([['order_id' => '9001', 'common_name' => 'shop.example.com', 'status' => 'expired', 'valid_till' => '2026-01-01']]);

    $result = app(SslProvisioningService::class)->importRemoteOrders('gogetssl');

    expect($result['updated'])->toBe(1);
    $order->refresh();
    expect($order->service_id)->toBe($owner->id)
        ->and($order->client_id)->toBe($owner->client_id)
        ->and($order->status)->toBe('Expired');
});

test('an account that cannot be read is reported, not counted as empty', function () {
    fakeGoGetSsl(['error' => true, 'message' => 'Invalid auth key']);

    $this->actingAs(sslsmAdmin(), 'admin')
        ->from(route('admin.ssl.index'))
        ->post(route('admin.ssl.import'))
        ->assertSessionHas('error', __('admin.ssl.import_failed'));
});

test('a completed certificate can be renewed from its page', function () {
    $service = sslsmService(sslsmProduct(['type' => 'ssl', 'ssl_module' => 'gogetssl']), ['status' => 'active']);
    $order = SslOrder::create([
        'remote_id' => '9003', 'module' => 'gogetssl', 'domain' => 'shop.example.com', 'status' => 'Completed',
        'cert' => '-----BEGIN CERTIFICATE-----X', 'service_id' => $service->id, 'client_id' => $service->client_id,
    ]);

    $this->actingAs(sslsmAdmin(), 'admin')
        ->get(route('admin.ssl.show', $order))
        ->assertOk()
        ->assertSee('value="renew"', false);
});
