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
 * Plans that are not for sale.
 *
 * A product can be hidden (a draft nobody meant to sell) or retired (a plan the
 * operator has stopped selling). The cart refuses both, with a comment saying
 * why: an old link would otherwise buy a discontinued plan. The order service
 * behind it never asked, and the order endpoint hands it product ids straight
 * from the request - so the rule held at one door and not the other, and an
 * integration could put a customer on a plan that is no longer sold.
 */
function orderApiFor(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function unsellableProduct(string $flag): Product
{
    return Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'auto_setup' => 'payment',
        $flag => true,
    ]);
}

it('refuses to order a retired plan', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $product = unsellableProduct('retired');

    $this->withHeaders(orderApiFor())
        ->postJson('/api/v1/addorder', [
            'clientid' => $client->id,
            'pid' => [$product->id],
            'billingcycle' => ['monthly'],
            'priceoverride' => [30],
        ])->assertStatus(422);

    expect(Order::where('client_id', $client->id)->count())->toBe(0)
        ->and(Service::where('product_id', $product->id)->count())->toBe(0);
});

it('refuses to order a hidden plan', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $product = unsellableProduct('hidden');

    $this->withHeaders(orderApiFor())
        ->postJson('/api/v1/addorder', [
            'clientid' => $client->id,
            'pid' => [$product->id],
            'billingcycle' => ['monthly'],
            'priceoverride' => [30],
        ])->assertStatus(422);

    expect(Order::where('client_id', $client->id)->count())->toBe(0);
});

it('still orders a plan that is for sale', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'auto_setup' => 'payment',
        'hidden' => false,
        'retired' => false,
    ]);

    $this->withHeaders(orderApiFor())
        ->postJson('/api/v1/addorder', [
            'clientid' => $client->id,
            'pid' => [$product->id],
            'billingcycle' => ['monthly'],
            'priceoverride' => [30],
        ])->assertSuccessful();

    expect(Order::where('client_id', $client->id)->count())->toBe(1);
});
