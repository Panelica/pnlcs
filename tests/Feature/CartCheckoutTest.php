<?php

use App\Models\Cart;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\User;
use App\Services\CartService;


function ensureDefaultCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['is_default' => true],
        [
            'code'       => 'USD',
            'prefix'     => '$',
            'suffix'     => '',
            'format'     => 1,
            'rate'       => '1.00000',
            'is_default' => true,
        ]
    );
}

function makeAuthenticatedClient(): array
{
    $user   = User::factory()->create();
    $client = Client::factory()->create(['email' => $user->email]);
    $user->clients()->attach($client->id, ['owner' => true, 'permissions' => null]);
    ensureDefaultCurrency();
    return [$user, $client];
}

function makeProductWithPricing(): Product
{
    $currency = ensureDefaultCurrency();
    $group    = ProductGroup::factory()->create(['hidden' => false]);
    $product  = Product::factory()->create([
        'group_id'            => $group->id,
        'hidden'              => false,
        'retired'             => false,
        'show_domain_options' => true,
    ]);

    Pricing::factory()->create([
        'type'         => 'product',
        'rel_id'       => $product->id,
        'currency_id'  => $currency->id,
        'monthly'      => 9.99,
        'quarterly'    => -1,
        'semiannually' => -1,
        'annually'     => 99.99,
        'biennially'   => -1,
        'triennially'  => -1,
    ]);

    return $product;
}

test('store page is publicly accessible', function () {
    ensureDefaultCurrency();
    ProductGroup::factory()->count(2)->create(['hidden' => false]);

    $response = $this->get(route('client.store'));
    $response->assertStatus(200);
    $response->assertSee('Order a New Product');
});

test('configure page shows billing cycles', function () {
    $product = makeProductWithPricing();

    $response = $this->get(route('client.store.configure', $product->slug));
    $response->assertStatus(200);
    $response->assertSee('Billing Cycle');
    $response->assertSee($product->name);
});

test('configure page returns 404 for hidden product', function () {
    ensureDefaultCurrency();
    $product = Product::factory()->create(['hidden' => true]);
    $this->get(route('client.store.configure', $product->slug))->assertStatus(404);
});

test('authenticated user can add product to cart', function () {
    [$user, $client] = makeAuthenticatedClient();
    $product         = makeProductWithPricing();

    $response = $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id'    => $product->id,
        'billing_cycle' => 'monthly',
        'domain'        => 'example.com',
    ]);

    $response->assertRedirect(route('client.cart.index'));
    $response->assertSessionHas('success');

    $cart = Cart::where('user_id', $client->id)->first();
    expect($cart)->not->toBeNull();
    $data  = json_decode($cart->data, true);
    $items = $data['items'];
    expect($items)->toHaveCount(1);
    expect($items[0]['product_id'])->toBe($product->id);
    expect($items[0]['billing_cycle'])->toBe('monthly');
    expect($items[0]['domain'])->toBe('example.com');
});

test('cart page shows cart contents', function () {
    [$user, $client] = makeAuthenticatedClient();
    $product         = makeProductWithPricing();

    $cartService = app(CartService::class);
    $cart        = $cartService->getOrCreateCart($client->id);
    $cartService->addProduct($cart, $product, 'monthly', 'test.com');

    $response = $this->actingAs($user)->get(route('client.cart.index'));
    $response->assertStatus(200);
    $response->assertSee($product->name);
    $response->assertSee('test.com');
});

test('can remove item from cart', function () {
    [$user, $client] = makeAuthenticatedClient();
    $product         = makeProductWithPricing();

    $cartService = app(CartService::class);
    $cart        = $cartService->getOrCreateCart($client->id);
    $cartService->addProduct($cart, $product, 'monthly');

    $response = $this->actingAs($user)->delete(route('client.cart.remove', 0));
    $response->assertRedirect(route('client.cart.index'));

    $cart->refresh();
    $data = json_decode($cart->data, true);
    expect($data['items'])->toHaveCount(0);
});

test('can apply valid promo code', function () {
    [$user, $client] = makeAuthenticatedClient();
    $product         = makeProductWithPricing();

    Promotion::factory()->percentage(10)->create([
        'code'            => 'SAVE10TEST',
        'start_date'      => now()->subDay(),
        'expiration_date' => now()->addMonth(),
        'max_uses'        => 0,
        'uses'            => 0,
    ]);

    $cartService = app(CartService::class);
    $cart        = $cartService->getOrCreateCart($client->id);
    $cartService->addProduct($cart, $product, 'monthly');

    $response = $this->actingAs($user)->post(route('client.cart.promo'), ['code' => 'SAVE10TEST']);
    $response->assertRedirect(route('client.cart.index'));
    $response->assertSessionHas('success');

    $cart->refresh();
    $data = json_decode($cart->data, true);
    expect($data['promo_code'])->toBe('SAVE10TEST');
});

test('invalid promo code shows error', function () {
    [$user] = makeAuthenticatedClient();

    $response = $this->actingAs($user)->post(route('client.cart.promo'), ['code' => 'INVALID999']);
    $response->assertRedirect(route('client.cart.index'));
    $response->assertSessionHas('error');
});

test('checkout page requires auth', function () {
    $this->get(route('client.cart.checkout'))->assertRedirect();
});

test('checkout redirects to cart when empty', function () {
    [$user] = makeAuthenticatedClient();

    $response = $this->actingAs($user)->get(route('client.cart.checkout'));
    $response->assertRedirect(route('client.cart.index'));
});

test('process checkout creates order and invoice', function () {
    [$user, $client] = makeAuthenticatedClient();
    $product         = makeProductWithPricing();

    $cartService = app(CartService::class);
    $cart        = $cartService->getOrCreateCart($client->id);
    $cartService->addProduct($cart, $product, 'monthly', 'newsite.com');

    $response = $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms'          => '1',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('orders', ['client_id' => $client->id, 'status' => 'Pending']);
    $this->assertDatabaseHas('invoices', ['client_id' => $client->id, 'status' => 'unpaid']);
    $this->assertDatabaseHas('services', ['client_id' => $client->id, 'domain' => 'newsite.com']);
});
