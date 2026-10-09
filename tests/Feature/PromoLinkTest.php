<?php

use App\Http\Middleware\PromoLink;
use App\Http\Middleware\RedirectToInstaller;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Services\CartService;

/*
 * A promotion in a link (?promo=CODE, or WHMCS's ?promocode=CODE) is
 * remembered and applied by the cart, so a campaign button or an email can
 * carry the discount without the customer typing a code.
 */

beforeEach(function () {
    $this->withoutMiddleware(RedirectToInstaller::class);
});

function plProduct(string $name, float $price = 100): Product
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'EUR', 'prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create(['hidden' => false])->id,
        'name' => $name, 'server_type' => null, 'auto_setup' => 'payment', 'tax' => false, 'hidden' => false, 'retired' => false,
    ]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id], ['monthly' => $price]);

    return $product;
}

function plPromo(string $code, array $attributes = []): Promotion
{
    return Promotion::create(array_merge([
        'code' => $code, 'type' => 'percentage', 'value' => 50, 'max_uses' => 0, 'uses' => 0,
        'start_date' => now()->subDay(), 'expiration_date' => now()->addYear(),
    ], $attributes));
}

function plAdd(Product $product): void
{
    test()->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);
}

function plCartPromo(): ?string
{
    $cart = app(CartService::class)->getOrCreateCart(null);

    return app(CartService::class)->calculateTotal($cart)['promo_code'] ?? null;
}

test('a code in the link is applied when the product reaches the cart', function () {
    plPromo('FIRSTYEAR');
    $product = plProduct('Starter');

    $this->get(route('client.store', ['promo' => 'FIRSTYEAR']))->assertOk();
    plAdd($product);

    $this->get(route('client.cart.index'))->assertOk()
        ->assertSee(__('client.cart.promo_applied', ['code' => 'FIRSTYEAR']));
    expect(session(PromoLink::SESSION))->toBeNull();
});

test('the WHMCS link name works too', function () {
    plPromo('WELCOME');
    $product = plProduct('Starter');

    $this->get(route('client.store.configure', ['product' => $product->slug, 'promocode' => 'WELCOME']));
    plAdd($product);
    $this->get(route('client.cart.index'));

    expect(plCartPromo())->toBe('WELCOME');
});

test('an unknown code is not remembered', function () {
    $this->get(route('client.store', ['promo' => 'NOSUCHCODE']))->assertOk();

    expect(session(PromoLink::SESSION))->toBeNull();
});

test('a code for another product waits until that product is in the cart', function () {
    $covered = plProduct('Covered');
    $other = plProduct('Other');
    plPromo('ONLYCOVERED', ['applies_to' => (string) $covered->id]);

    $this->get(route('client.store', ['promo' => 'ONLYCOVERED']));
    plAdd($other);
    $this->get(route('client.cart.index'));
    expect(plCartPromo())->toBeNull()->and(session(PromoLink::SESSION))->toBe('ONLYCOVERED');

    plAdd($covered);
    $this->get(route('client.cart.index'));
    expect(plCartPromo())->toBe('ONLYCOVERED');
});

test('a code the customer typed is not replaced by the link', function () {
    plPromo('TYPED');
    plPromo('LINKED');
    $product = plProduct('Starter');

    plAdd($product);
    $this->post(route('client.cart.promo'), ['code' => 'TYPED']);
    $this->get(route('client.store', ['promo' => 'LINKED']));
    $this->get(route('client.cart.index'));

    expect(plCartPromo())->toBe('TYPED');
});
