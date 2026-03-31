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

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function ensureDefaultCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['is_default' => true],
        [
            'code'       => 'USD',
            'prefix'     => '$',
            'suffix'     => '',
            'rate'       => 1.0,
            'is_default' => true,
        ]
    );
}

function makeAuthenticatedClient(): array
{
    $currency = ensureDefaultCurrency();

    $user = User::factory()->create([
        'email'    => 'cartuser_' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    // clients table has NO user_id column — relationship is via user_client pivot
    $client = Client::factory()->create(['email' => $user->email]);
    $user->clients()->attach($client->id, ['owner' => true, 'permissions' => null]);

    return [$user, $client, $currency];
}

function makeProductWithPricing(Currency $currency): Product
{
    // product_groups table has NO currency_id column
    // products table uses 'group_id' (not 'product_group_id')
    $group   = ProductGroup::factory()->create();
    $product = Product::factory()->create([
        'group_id' => $group->id,
        'hidden'   => false,
        'retired'  => false,
    ]);

    Pricing::factory()->create([
        'type'         => 'product',
        'rel_id'       => $product->id,
        'currency_id'  => $currency->id,
        'monthly'      => 9.99,
        'quarterly'    => -1,
        'semiannually' => -1,
        'annually'     => -1,
        'biennially'   => -1,
        'triennially'  => -1,
    ]);

    return $product;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test('store page is publicly accessible', function () {
    ensureDefaultCurrency();
    $response = $this->get(route('client.store'));
    $response->assertStatus(200);
});

test('configure page shows billing cycles', function () {
    [$user, $client, $currency] = makeAuthenticatedClient();
    $product = makeProductWithPricing($currency);

    $response = $this->actingAs($user)->get(route('client.store.configure', $product));
    $response->assertStatus(200);
    $response->assertSee('Monthly');
});

test('configure page returns 404 for hidden product', function () {
    $currency = ensureDefaultCurrency();
    $group    = ProductGroup::factory()->create();
    $product  = Product::factory()->create(['group_id' => $group->id, 'hidden' => true]);

    $response = $this->get(route('client.store.configure', $product));
    $response->assertStatus(404);
});

test('authenticated user can add product to cart', function () {
    [$user, $client, $currency] = makeAuthenticatedClient();
    $product = makeProductWithPricing($currency);

    $response = $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id'    => $product->id,
        'billing_cycle' => 'monthly',
        'domain'        => 'example.com',
    ]);

    $response->assertRedirect(route('client.cart.index'));
    $response->assertSessionHas('success');

    $cart = Cart::where('user_id', $client->id)->first();
    expect($cart)->not->toBeNull();
    $data = json_decode($cart->data, true);
    expect($data['items'])->toHaveCount(1);
    expect($data['items'][0]['product_id'])->toBe($product->id);
});

test('cart page shows cart contents', function () {
    [$user, $client, $currency] = makeAuthenticatedClient();
    $product     = makeProductWithPricing($currency);
    $cartService = app(CartService::class);
    $cart        = $cartService->getOrCreateCart($client->id);
    $cartService->addProduct($cart, $product, 'monthly', 'test.com');

    $response = $this->actingAs($user)->get(route('client.cart.index'));
    $response->assertStatus(200);
    $response->assertSee($product->name);
    $response->assertSee('test.com');
});

test('can remove item from cart', function () {
    [$user, $client, $currency] = makeAuthenticatedClient();
    $product     = makeProductWithPricing($currency);
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
    [$user, $client, $currency] = makeAuthenticatedClient();
    $product   = makeProductWithPricing($currency);
    $promoCode = 'SAVE10_' . uniqid();

    Promotion::factory()->create([
        'code'            => $promoCode,
        'type'            => 'percentage',
        'value'           => 10,
        'max_uses'        => 0,
        'uses'            => 0,
        'start_date'      => now()->subDay(),
        'expiration_date' => now()->addDay(),
    ]);

    $cartService = app(CartService::class);
    $cart        = $cartService->getOrCreateCart($client->id);
    $cartService->addProduct($cart, $product, 'monthly');

    $response = $this->actingAs($user)->post(route('client.cart.promo'), ['code' => $promoCode]);
    $response->assertRedirect(route('client.cart.index'));
    $response->assertSessionHas('success');

    $cart->refresh();
    $data = json_decode($cart->data, true);
    expect($data['promo_code'] ?? null)->toBe($promoCode);
});

test('invalid promo code shows error', function () {
    [$user] = makeAuthenticatedClient();

    $response = $this->actingAs($user)->post(route('client.cart.promo'), ['code' => 'INVALID_DOESNOTEXIST']);
    $response->assertRedirect(route('client.cart.index'));
    $response->assertSessionHas('error');
});

test('checkout page requires auth', function () {
    $response = $this->get(route('client.cart.checkout'));
    expect($response->getStatusCode())->toBeIn([301, 302, 303, 307, 308]);
});

test('checkout redirects to cart when empty', function () {
    [$user] = makeAuthenticatedClient();

    $response = $this->actingAs($user)->get(route('client.cart.checkout'));
    $response->assertRedirect(route('client.cart.index'));
});

test('process checkout creates order and invoice', function () {
    [$user, $client, $currency] = makeAuthenticatedClient();
    $product     = makeProductWithPricing($currency);
    $cartService = app(CartService::class);
    $cart        = $cartService->getOrCreateCart($client->id);
    $domainName  = 'newsite_' . uniqid() . '.com';
    $cartService->addProduct($cart, $product, 'monthly', $domainName);

    $response = $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms'          => '1',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('orders', ['client_id' => $client->id, 'status' => 'Pending']);
    $this->assertDatabaseHas('invoices', ['client_id' => $client->id, 'status' => 'unpaid']);
    $this->assertDatabaseHas('services', ['client_id' => $client->id, 'domain' => $domainName]);
});
