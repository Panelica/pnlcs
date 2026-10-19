<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\DomainPricing;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/*
 * A registry will not register a domain without the registrant's phone
 * number (ICANN requires one for every gTLD contact). Checkout asked for it
 * only where the seller is in Turkey, so a seller elsewhere took the payment
 * and the registration was then refused: DomainNameAPI answered HTTP 400 and
 * the paid domain stayed pending.
 */

function phoneShopper(bool $withDomain, ?string $phone = null): User
{
    Setting::set('Country', 'GB', 'general');
    Currency::firstOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    DomainPricing::updateOrCreate(['extension' => '.com'], [
        'register_price' => 12, 'transfer_price' => 10, 'renew_price' => 14,
        'min_years' => 1, 'max_years' => 10, 'enabled' => true,
    ]);

    $user = User::factory()->create();
    $client = Client::factory()->create(['tax_exempt' => true, 'phone_number' => $phone]);
    $user->clients()->attach($client->id);

    $cart = app(CartService::class)->getOrCreateCart($client->id);
    if ($withDomain) {
        app(CartService::class)->addDomain($cart, 'needs-a-phone.com', 'register', 1);
    }

    return $user;
}

test('a domain order asks for the phone number when the account has none', function () {
    $user = phoneShopper(withDomain: true);

    $html = $this->actingAs($user)->get(route('client.cart.checkout'))->assertOk()->getContent();
    expect($html)->toContain('name="phone_number"');

    $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => 'on',
    ])->assertSessionHasErrors('phone_number');

    expect(Order::count())->toBe(0);
});

test('the phone number given at checkout is kept on the account', function () {
    Mail::fake();
    $user = phoneShopper(withDomain: true);
    $client = $user->clients()->first();

    $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => 'on',
        'phone_number' => '+44 20 7946 0000',
        'address1' => $client->address1, 'city' => $client->city,
        'postcode' => $client->postcode, 'country' => $client->country,
    ])->assertSessionHasNoErrors();

    expect($client->fresh()->phone_number)->toBe('+44 20 7946 0000')
        ->and(Order::count())->toBe(1);
});

test('an account that already has a phone number is not asked again', function () {
    $user = phoneShopper(withDomain: true, phone: '+44 20 7946 0001');

    $html = $this->actingAs($user)->get(route('client.cart.checkout'))->assertOk()->getContent();

    expect($html)->not->toContain('name="phone_number"');
});

test('an order without a domain does not ask for a phone outside Turkey', function () {
    $user = phoneShopper(withDomain: false);
    $product = \App\Models\Product::factory()->create([
        'group_id' => \App\Models\ProductGroup::factory()->create()->id, 'hidden' => false, 'retired' => false, 'tax' => false,
    ]);
    \App\Models\Pricing::create(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => Currency::getDefault()->id, 'monthly' => 10]);
    $client = $user->clients()->first();
    app(CartService::class)->addProduct(app(CartService::class)->getOrCreateCart($client->id), $product, 'monthly', 'no-phone.example');

    $html = $this->actingAs($user)->get(route('client.cart.checkout'))->assertOk()->getContent();

    expect($html)->not->toContain('name="phone_number"');
});
