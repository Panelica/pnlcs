<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Acting on an order through the API.
 *
 * Every endpoint here flipped the status column and stopped. The same actions
 * in the admin screen go through OrderService, which is what actually accepts,
 * cancels or holds an order - so an integration doing this by API changed the
 * word on the screen and nothing else: accepting provisioned nothing,
 * cancelling left the services running and the invoice owing, and calling an
 * order fraudulent left the fraudster's site serving.
 */
function orderApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function apiOrderWithService(string $orderStatus = 'pending', string $serviceStatus = 'pending'): array
{
    $client = Client::factory()->create();
    $server = Server::factory()->create([
        'type' => 'cpanel', 'hostname' => 'whm.orderapi.test',
        'access_hash' => 'token-123', 'active' => true,
    ]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'cpanel',
        'auto_setup' => 'order',
    ]);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 25]);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'status' => $orderStatus,
        'invoice_id' => $invoice->id,
    ]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'order_id' => $order->id,
        'username' => 'orderapi',
        'domain' => 'orderapi.test',
        'status' => $serviceStatus,
    ]);

    return [$order, $service, $invoice];
}

test('accepting an order through the api provisions it', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);

    [$order, $service] = apiOrderWithService();

    $this->withHeaders(orderApiHeaders())
        ->postJson('/api/v1/acceptorder', ['orderid' => $order->id])
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'createacct'));
    expect(strtolower($order->fresh()->status))->toBe('active');
});

test('cancelling an order through the api stops what it created', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);

    [$order, $service, $invoice] = apiOrderWithService('pending', 'active');

    $this->withHeaders(orderApiHeaders())
        ->postJson('/api/v1/cancelorder', ['orderid' => $order->id])
        ->assertSuccessful();

    expect(strtolower($order->fresh()->status))->toBe('cancelled')
        ->and(strtolower($service->fresh()->status))->toBe('cancelled')
        ->and(strtolower($invoice->fresh()->status))->toBe('cancelled');
});

test('calling an order fraudulent through the api stops the account', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);

    [$order, $service] = apiOrderWithService('pending', 'active');

    $this->withHeaders(orderApiHeaders())
        ->postJson('/api/v1/fraudorder', ['orderid' => $order->id])
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'suspendacct'));
    expect(strtolower($order->fresh()->status))->toBe('fraud');
});

test('deleting an order through the api does not leave its services behind', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']], 200)]);

    [$order, $service] = apiOrderWithService('pending', 'active');

    $this->withHeaders(orderApiHeaders())
        ->postJson('/api/v1/deleteorder', ['orderid' => $order->id])
        ->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('cancelled');
});
