<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\OrderService;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Mail;

/**
 * The domain an order is placed for.
 *
 * The cart reads whatever was typed through Domain::normalise, so a pasted URL
 * becomes the name it points at. Orders are built by OrderService, which wrote
 * the string it was handed - and the order endpoint hands it the request
 * verbatim. So the same address arrived one way through the shop and another
 * through the API, and a scheme, a www or a trailing dot would be written onto
 * the service and passed on to the panel and the registrar as part of the name.
 */
function orderingHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

it('writes the ordered service against the domain it names', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'auto_setup' => 'payment',
    ]);

    $this->withHeaders(orderingHeaders())
        ->postJson('/api/v1/addorder', [
            'clientid' => $client->id,
            'pid' => [$product->id],
            'domain' => [' HTTPS://WWW.MySite.COM/pricing '],
            'billingcycle' => ['monthly'],
            'priceoverride' => [30],
        ])->assertSuccessful();

    expect(Service::where('client_id', $client->id)->value('domain'))->toBe('mysite.com');
});

it('registers the domain it names', function () {
    Mail::fake();

    $client = Client::factory()->create();

    app(OrderService::class)->processOrder($client, [[
        'type' => 'domain',
        'domain' => ' WWW.Example.COM. ',
        'domain_type' => 'Register',
        'registration_period' => 1,
        'amount' => 12.99,
        'renewal_amount' => 14.99,
    ]], 'banktransfer');

    expect(Domain::where('client_id', $client->id)->value('domain'))->toBe('example.com');
});

it('leaves a plain domain alone', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'auto_setup' => 'payment',
    ]);

    app(OrderService::class)->processOrder($client, [[
        'type' => 'service',
        'product_id' => $product->id,
        'domain' => 'plain-example.com',
        'billing_cycle' => 'monthly',
        'amount' => 10,
    ]], 'banktransfer');

    expect(Service::where('client_id', $client->id)->value('domain'))->toBe('plain-example.com')
        ->and(Order::where('client_id', $client->id)->count())->toBe(1);
});
