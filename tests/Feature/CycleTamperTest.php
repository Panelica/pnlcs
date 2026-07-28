<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * The order form only offers the cycles a product is priced for, but the
 * request that adds it to the cart accepted any cycle in the enum. Posting an
 * unpriced one therefore bought the product for nothing.
 */
test('a billing cycle the product is not priced for cannot be ordered', function () {
    Mail::fake();

    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
        'hidden' => false,
        'retired' => false,
    ]);
    // Sold by the year only.
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 0, 'annually' => 200]
    );

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'domain' => 'free-hosting.com',
    ])->assertSessionHasErrors();

    expect(Order::count())->toBe(0)
        ->and(Service::count())->toBe(0)
        ->and(Invoice::count())->toBe(0);
});
