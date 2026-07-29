<?php

use App\Models\Admin;
use App\Models\Cart;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/**
 * Promotion rules.
 *
 * The promotions table carries the rules an operator would expect — which
 * products a code covers, one use per customer, new customers only, existing
 * customers only — and none of them were read or settable. Every code applied
 * to everything, for everyone, as often as they liked up to the global limit.
 */
function promoProduct(string $name = 'Plan A', float $price = 100): Product
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create(['hidden' => false])->id,
        'name' => $name,
        'server_type' => null,
        'auto_setup' => 'payment',
        'tax' => false,
        'hidden' => false,
        'retired' => false,
    ]);

    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => $price]
    );

    return $product;
}

function promoCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['tax_exempt' => true]);
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function cartWith(Client $client, Product $product): Cart
{
    $cart = app(CartService::class)->getOrCreateCart($client->id);
    app(CartService::class)->addProduct($cart, $product, 'monthly', 'promo-'.uniqid().'.com');

    return $cart;
}

function makePromo(array $attributes = []): Promotion
{
    return Promotion::create(array_merge([
        'code' => 'SAVE10',
        'type' => 'percentage',
        'value' => 10,
        'max_uses' => 0,
        'uses' => 0,
        'start_date' => now()->subDay(),
        'expiration_date' => now()->addYear(),
    ], $attributes));
}

test('a code with no rules still works for anyone', function () {
    [, $client] = promoCustomer();
    makePromo();
    $cart = cartWith($client, promoProduct());

    $result = app(CartService::class)->applyPromoCode($cart, 'SAVE10');

    expect($result['success'] ?? false)->toBeTrue();
});

test('a code tied to one product is refused on another', function () {
    [, $client] = promoCustomer();
    $covered = promoProduct('Covered Plan');
    $other = promoProduct('Other Plan');

    makePromo(['applies_to' => (string) $covered->id]);
    $cart = cartWith($client, $other);

    expect(app(CartService::class)->applyPromoCode($cart, 'SAVE10')['success'] ?? false)->toBeFalse();
});

test('a code tied to one product works on that product', function () {
    [, $client] = promoCustomer();
    $covered = promoProduct('Covered Plan');

    makePromo(['applies_to' => (string) $covered->id]);
    $cart = cartWith($client, $covered);

    expect(app(CartService::class)->applyPromoCode($cart, 'SAVE10')['success'] ?? false)->toBeTrue();
});

test('a one-per-customer code cannot be used twice by the same customer', function () {
    Mail::fake();
    [$user, $client] = promoCustomer();
    $product = promoProduct();
    makePromo(['apply_once' => true]);

    $cart = cartWith($client, $product);
    app(CartService::class)->applyPromoCode($cart, 'SAVE10');
    app(CartService::class)->checkout($cart, $client->id, 'banktransfer');

    $second = cartWith($client, $product);

    expect(app(CartService::class)->applyPromoCode($second, 'SAVE10')['success'] ?? false)->toBeFalse();
});

test('a new-customers-only code is refused once they have ordered before', function () {
    Mail::fake();
    [, $client] = promoCustomer();
    $product = promoProduct();
    makePromo(['new_signups_only' => true]);

    // Their first order, placed without the code.
    app(CartService::class)->checkout(cartWith($client, $product), $client->id, 'banktransfer');

    $cart = cartWith($client, $product);

    expect(app(CartService::class)->applyPromoCode($cart, 'SAVE10')['success'] ?? false)->toBeFalse();
});

test('a new-customers-only code works for someone ordering for the first time', function () {
    [, $client] = promoCustomer();
    makePromo(['new_signups_only' => true]);
    $cart = cartWith($client, promoProduct());

    expect(app(CartService::class)->applyPromoCode($cart, 'SAVE10')['success'] ?? false)->toBeTrue();
});

test('an existing-customers-only code is refused to a brand new one', function () {
    [, $client] = promoCustomer();
    makePromo(['existing_client' => true]);
    $cart = cartWith($client, promoProduct());

    expect(app(CartService::class)->applyPromoCode($cart, 'SAVE10')['success'] ?? false)->toBeFalse();
});

test('an operator can set the rules on the promotions screen', function () {
    $admin = Admin::factory()->create();
    $product = promoProduct();

    $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code' => 'RULES1',
        'type' => 'percentage',
        'value' => 15,
        'applies_to' => [$product->id],
        'apply_once' => '1',
        'new_signups_only' => '1',
    ])->assertRedirect();

    $promo = Promotion::where('code', 'RULES1')->firstOrFail();

    expect((bool) $promo->apply_once)->toBeTrue()
        ->and((bool) $promo->new_signups_only)->toBeTrue()
        ->and($promo->applies_to)->toBe((string) $product->id);
});
