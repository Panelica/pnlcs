<?php

use App\Models\Cart;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;

/**
 * The domain a customer types.
 *
 * The domain search lower-cased what it was given; the order form, where a
 * customer names the site their hosting is for, kept whatever was typed. The
 * same address therefore arrived in two different shapes depending on which
 * box it was entered in, and a pasted URL - which is what people paste - was
 * stored with its scheme and its www still attached and handed to the panel
 * and the registrar like that.
 */
function domainShopper(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'show_domain_options' => true,
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

    return [$user, $product];
}

it('reads a pasted address as the domain it names', function () {
    expect(Domain::normalise('  HTTPS://WWW.MySite.COM/somewhere?a=1  '))->toBe('mysite.com')
        ->and(Domain::normalise('Example.COM.'))->toBe('example.com')
        ->and(Domain::normalise('www.sub.example.co.uk'))->toBe('sub.example.co.uk')
        ->and(Domain::normalise(null))->toBe('');
});

it('leaves a domain that is already plain alone', function () {
    expect(Domain::normalise('example.com'))->toBe('example.com')
        ->and(Domain::normalise('wwwshop.com'))->toBe('wwwshop.com');
});

it('stores the ordered domain the same way the search box would', function () {
    [$user, $product] = domainShopper();

    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'domain' => ' WWW.MySite.COM ',
        'domain_option' => 'own',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $cart = Cart::latest('id')->firstOrFail();
    $data = is_array($cart->data) ? $cart->data : (json_decode((string) $cart->data, true) ?: []);

    expect(collect($data['items'] ?? [])->pluck('domain')->filter()->first())->toBe('mysite.com');
});
