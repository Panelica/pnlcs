<?php

use App\Models\Cart;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;

/*
 * A visitor buys without an account, and opens one at the payment step.
 *
 * The old shape: configure a product, press "add to cart", hit a login wall,
 * lose the whole configuration, register, start over. The wall stood at the
 * most expensive moment in the funnel. CartService could always key a cart by
 * bare session - only the routes forbade guests - so the fix was to let them
 * through and open the account inside checkout, where the visitor is
 * committed.
 */

function guestProduct(): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => '',
        'type' => 'other',
        'hidden' => false,
        'retired' => false,
    ]);

    Pricing::create([
        'type' => 'product',
        'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->create()->id,
        'rel_id' => $product->id,
        'monthly' => 10,
    ]);

    return $product;
}

test('a visitor can put a product in the cart without logging in', function () {
    $product = guestProduct();

    $this->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
    ])->assertRedirect()->assertSessionMissing('errors');

    $this->get(route('client.cart.index'))->assertOk();

    // The cart belongs to the session, and to nobody yet.
    expect(Cart::whereNull('user_id')->count())->toBe(1);
});

test('checkout shows the visitor an account form, not a login wall', function () {
    $product = guestProduct();
    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);

    $html = $this->get(route('client.cart.checkout'))->assertOk()->getContent();

    expect($html)->toContain(__('client.auth.create_your_account'))
        ->and($html)->toContain('name="password_confirmation"')
        // The other door stays open: an existing customer signs in instead.
        ->and($html)->toContain(__('client.auth.already_have_account'));
});

test('paying opens the account, and the order follows once the address is confirmed', function () {
    $product = guestProduct();
    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);

    $response = $this->post(route('client.cart.process'), [
        'first_name' => 'Guest',
        'last_name' => 'Buyer',
        'email' => 'guest.buyer@example.test',
        'password' => 'secret-enough',
        'password_confirmation' => 'secret-enough',
        // The invoice has to be issued to somewhere, so checkout asks.
        'address1' => '12 Market Street',
        'city' => 'Istanbul',
        'postcode' => '34000',
        'country' => 'TR',
        'payment_method' => 'banktransfer',
        'terms' => '1',
    ]);

    // The account is opened and the visitor is signed in, but the order waits:
    // a service is not sold to an address nobody has proved yet.
    $response->assertRedirect(route('client.verification.notice'))->assertSessionMissing('errors');

    $client = Client::where('email', 'guest.buyer@example.test')->first();
    $user = User::where('email', 'guest.buyer@example.test')->first();

    expect($client)->not->toBeNull()
        ->and($user)->not->toBeNull()
        ->and(auth()->check())->toBeTrue()                       // logged in, not bounced
        ->and(Order::where('client_id', $client->id)->count())->toBe(0)
        // Stored, not dropped on the floor: the tax rate and the invoice PDF
        // both read these back.
        ->and($client->address1)->toBe('12 Market Street')
        ->and($client->city)->toBe('Istanbul')
        ->and($client->postcode)->toBe('34000')
        ->and($client->country)->toBe('TR');

    // They confirm, come back to the checkout their cart is still on, and the
    // order goes through.
    $user->markEmailAsVerified();

    // The guard is holding the user object it signed in a moment ago; the
    // next request in a real browser reads the row again.
    $this->actingAs($user->fresh());

    $this->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasNoErrors();

    expect(Order::where('client_id', $client->id)->count())->toBe(1);
});

test('the guest cart survives logging in instead', function () {
    $product = guestProduct();
    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);

    $user = User::factory()->create(['password' => bcrypt('secret-enough')]);
    $user->clients()->attach($client = Client::factory()->create());

    // Login rotates the session id the guest cart is keyed by; the cart is
    // handed to the account before that happens. This is the moment it used
    // to evaporate.
    $this->post(route('client.login.submit'), [
        'email' => $user->email,
        'password' => 'secret-enough',
    ]);

    $cart = Cart::first();
    expect($cart->user_id)->toBe($client->id);
});

test('a banned address cannot buy its way past the register ban', function () {
    App\Models\BannedEmail::create(['email' => 'banned@example.test', 'domain' => 'example.test', 'reason' => 'test']);
    $product = guestProduct();
    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);

    $this->post(route('client.cart.process'), [
        'first_name' => 'B', 'last_name' => 'B',
        'email' => 'banned@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'banned@example.test')->exists())->toBeFalse();
});

test('a logged-in customer checks out exactly as before', function () {
    $product = guestProduct();
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create());

    $this->actingAs($user)
        ->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);

    $html = $this->actingAs($user)->get(route('client.cart.checkout'))->assertOk()->getContent();

    // The account form is for visitors; a customer sees their own details.
    expect($html)->not->toContain('name="password_confirmation"')
        ->and($html)->toContain(__('client.checkout.contact_details'));
});
