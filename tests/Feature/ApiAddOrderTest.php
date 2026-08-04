<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Mail;

/**
 * Placing an order through the API.
 *
 * The endpoint took a customer and made an order with nothing on it: no
 * service, no domain, no invoice, and no other endpoint that could add one
 * afterwards. An integration was handed an order id for something that could
 * never be provisioned or paid for, and accepting it later did nothing because
 * there was nothing to provision.
 */
function addOrderApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

test('an order placed through the api has what was ordered on it', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'auto_setup' => 'payment',
    ]);

    $response = $this->withHeaders(addOrderApiHeaders())
        ->postJson('/api/v1/addorder', [
            'clientid' => $client->id,
            'paymentmethod' => 'banktransfer',
            'pid' => [$product->id],
            'domain' => ['ordered-by-api.test'],
            'billingcycle' => ['monthly'],
            'priceoverride' => [30],
        ])->assertSuccessful();

    $order = Order::findOrFail($response->json('orderid'));

    expect($order->invoice_id)->not->toBeNull()
        ->and(Service::where('order_id', $order->id)->count())->toBe(1)
        ->and(round((float) $order->amount, 2))->toBe(30.00);
});

test('an order with nothing on it is refused rather than created empty', function () {
    Mail::fake();

    $client = Client::factory()->create();

    $this->withHeaders(addOrderApiHeaders())
        ->postJson('/api/v1/addorder', [
            'clientid' => $client->id,
            'paymentmethod' => 'banktransfer',
        ])->assertStatus(422);

    expect(Order::where('client_id', $client->id)->count())->toBe(0);
});
