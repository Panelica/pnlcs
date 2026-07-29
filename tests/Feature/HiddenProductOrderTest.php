<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Products the shop is not offering.
 *
 * The configure page refuses a hidden or retired product, and the listing
 * leaves them out — but the request that puts one in the cart only checked
 * that the id exists. An old link, a guessed id or a saved form was enough to
 * buy a discontinued plan, or a draft one that was never meant to be sold.
 */
function shopProduct(array $attributes): Product
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create(array_merge([
        'group_id' => ProductGroup::factory()->create(['hidden' => false])->id,
        'server_type' => null,
        'auto_setup' => 'payment',
        'tax' => false,
    ], $attributes));

    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 20]
    );

    return $product;
}

function shopper(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('a hidden product cannot be put in the cart', function () {
    $product = shopProduct(['hidden' => true, 'retired' => false]);

    $this->actingAs(shopper())->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'domain' => 'sneaky.com',
    ])->assertSessionHasErrors();

    $this->actingAs(shopper())->get(route('client.cart.index'))->assertOk()->assertDontSee($product->name);
});

test('a retired product cannot be put in the cart', function () {
    $product = shopProduct(['hidden' => false, 'retired' => true]);

    $this->actingAs(shopper())->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'domain' => 'discontinued.com',
    ])->assertSessionHasErrors();
});

test('a hidden product cannot be ordered even if it reaches checkout', function () {
    Mail::fake();
    $user = shopper();
    $product = shopProduct(['hidden' => true, 'retired' => false]);

    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'domain' => 'sneaky.com',
    ]);

    $this->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => '1',
    ]);

    expect(Order::count())->toBe(0);
});

test('a product the shop is offering still goes in', function () {
    $product = shopProduct(['hidden' => false, 'retired' => false]);

    $this->actingAs(shopper())->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'domain' => 'normal.com',
    ])->assertRedirect()->assertSessionHasNoErrors();
});

test('the shop does not list what it will not sell', function () {
    $hidden = shopProduct(['hidden' => true, 'retired' => false, 'name' => 'Hidden Plan']);
    $retired = shopProduct(['hidden' => false, 'retired' => true, 'name' => 'Retired Plan']);
    $live = shopProduct(['hidden' => false, 'retired' => false, 'name' => 'Live Plan']);

    $this->actingAs(shopper())->get(route('client.store'))
        ->assertOk()
        ->assertSee($live->name)
        ->assertDontSee($hidden->name)
        ->assertDontSee($retired->name);
});
