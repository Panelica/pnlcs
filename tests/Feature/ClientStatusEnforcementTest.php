<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/**
 * Closing a customer's account.
 *
 * The admin screen offers active, inactive and closed, and closing one from
 * the service cancels their services — and then nothing read the status ever
 * again. The customer could still sign in, still order more hosting and still
 * be billed for it. One account is marked closed on this installation.
 */
function statusClient(string $status): array
{
    $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
    $client = Client::factory()->create(['status' => $status, 'tax_exempt' => true]);
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function sellableProduct(): Product
{
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

    return $product;
}

function fillCart(Client $client): void
{
    $cart = app(CartService::class)->getOrCreateCart($client->id);
    app(CartService::class)->addProduct($cart, sellableProduct(), 'monthly', 'status-check.example');
}

test('a closed account cannot sign in', function () {
    [$user] = statusClient('closed');

    $this->post(route('client.login.submit'), [
        'email' => $user->email,
        'password' => 'secret-pass',
    ])->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

test('an active account signs in as before', function () {
    [$user] = statusClient('active');

    $this->post(route('client.login.submit'), [
        'email' => $user->email,
        'password' => 'secret-pass',
    ])->assertRedirect();

    expect(auth()->check())->toBeTrue();
});

test('an account that is not active cannot buy anything more', function () {
    Mail::fake();
    [$user, $client] = statusClient('inactive');
    fillCart($client);

    $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => 'on',
    ])->assertSessionHasErrors();

    expect(Order::count())->toBe(0);
});

test('an active account still checks out', function () {
    Mail::fake();
    [$user, $client] = statusClient('active');
    fillCart($client);

    $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => 'on',
    ])->assertRedirect();

    expect(Order::count())->toBe(1);
});
