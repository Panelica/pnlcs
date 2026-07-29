<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/**
 * The payment method a customer may choose.
 *
 * Checkout validated it as "required|string" and took whatever arrived, so an
 * order could be placed against a gateway that was switched off, or one that
 * does not exist at all. The name is written onto the order, the invoice and
 * the service, and a refund later looks the gateway up by that name.
 */
function checkoutShopper(): User
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['tax_exempt' => true]);
    $user->clients()->attach($client->id);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'hidden' => false,
        'retired' => false,
        'tax' => false,
    ]);

    Pricing::create([
        'type' => 'product',
        'rel_id' => $product->id,
        'currency_id' => Currency::firstOrCreate(
            ['code' => 'USD'],
            ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]
        )->id,
        'monthly' => 10,
    ]);

    $cart = app(CartService::class)->getOrCreateCart($client->id);
    app(CartService::class)->addProduct($cart, $product, 'monthly', 'checkout.example');

    return $user;
}

function switchOn(string $gateway): void
{
    GatewaySettings::updateOrCreate(
        ['gateway' => $gateway, 'setting' => 'active'],
        ['value' => '1']
    );
}

test('a gateway that does not exist is refused', function () {
    Mail::fake();

    $this->actingAs(checkoutShopper())->post(route('client.cart.process'), [
        'payment_method' => 'not-a-gateway',
        'terms' => 'on',
    ])->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});

test('a gateway that is switched off is refused', function () {
    Mail::fake();
    switchOn('mollie');

    $this->actingAs(checkoutShopper())->post(route('client.cart.process'), [
        'payment_method' => 'stripe',
        'terms' => 'on',
    ])->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});

test('a gateway that is switched on goes through', function () {
    Mail::fake();
    switchOn('mollie');

    $this->actingAs(checkoutShopper())->post(route('client.cart.process'), [
        'payment_method' => 'mollie',
        'terms' => 'on',
    ])->assertRedirect();

    expect(Order::count())->toBe(1)
        ->and(Order::first()->payment_method)->toBe('mollie');
});

test('with nothing switched on bank transfer is still accepted', function () {
    Mail::fake();

    $this->actingAs(checkoutShopper())->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => 'on',
    ])->assertRedirect();

    expect(Order::count())->toBe(1);
});
