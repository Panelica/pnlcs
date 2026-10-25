<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Services\CartService;

/*
 * A promotion could be limited to products but not to the billing period:
 * "60% off when you pay for a year" was also 60% off a single month, so a
 * customer could take the first month for a fraction and leave. A code can
 * now name the periods it applies to; with none named it applies to every
 * period, as every existing code does.
 */

function pbcProduct(array $prices): Product
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false]);
    $currency = Currency::getDefault()?->id ?? Currency::factory()->create(['is_default' => true])->id;
    Pricing::create(array_merge(['type' => 'product', 'currency_id' => $currency, 'rel_id' => $product->id], $prices));

    return $product;
}

function pbcCart(Product $product, string $cycle): array
{
    $client = Client::factory()->create(['tax_exempt' => true]);
    $carts = app(CartService::class);
    $cart = $carts->getOrCreateCart($client->id);
    $carts->addProduct($cart, $product, $cycle, 'site.example.com');

    return [$client, $carts, $cart];
}

test('a code for yearly payment discounts a year and is refused on a month', function () {
    $product = pbcProduct(['monthly' => 10, 'annually' => 100]);
    Promotion::create(['code' => 'YEAR60', 'type' => 'percentage', 'value' => 60, 'billing_cycles' => 'annually,biennially']);

    [$client, $carts, $cart] = pbcCart($product, 'annually');
    expect($carts->applyPromoCode($cart, 'YEAR60')['success'])->toBeTrue();
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');
    expect($quoted['discount'])->toBe(60.0)->and((float) $order->invoice->total)->toBe(40.0);

    [, $carts, $monthly] = pbcCart($product, 'monthly');
    expect($carts->applyPromoCode($monthly, 'YEAR60')['success'])->toBeFalse()
        ->and($carts->calculateTotal($monthly->fresh())['discount'])->toBe(0.0);
});

test('in a mixed basket only the lines on an allowed period are discounted, in the cart and on the invoice', function () {
    $yearly = pbcProduct(['monthly' => 10, 'annually' => 100]);
    $monthly = pbcProduct(['monthly' => 20, 'annually' => 200]);
    Promotion::create(['code' => 'YEAR50', 'type' => 'percentage', 'value' => 50, 'billing_cycles' => 'annually']);

    [$client, $carts, $cart] = pbcCart($yearly, 'annually');
    $carts->addProduct($cart, $monthly, 'monthly', 'other.example.com');
    $carts->applyPromoCode($cart, 'YEAR50');

    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    expect($quoted['discount'])->toBe(50.0)
        ->and((float) $order->invoice->total)->toBe(70.0);
});

test('a code with no period named still applies to every period', function () {
    $product = pbcProduct(['monthly' => 10]);
    Promotion::create(['code' => 'ANY10', 'type' => 'percentage', 'value' => 10]);

    [, $carts, $cart] = pbcCart($product, 'monthly');

    expect($carts->applyPromoCode($cart, 'ANY10')['success'])->toBeTrue()
        ->and($carts->calculateTotal($cart->fresh())['discount'])->toBe(1.0);
});

test('periods are read whatever their spelling', function () {
    $promo = new Promotion(['billing_cycles' => 'annually,biennially']);

    expect($promo->coversCycle('Annually'))->toBeTrue()
        ->and($promo->coversCycle('Semi-Annually'))->toBeFalse()
        ->and((new Promotion(['billing_cycles' => 'semiannually']))->coversCycle('Semi-Annually'))->toBeTrue()
        ->and((new Promotion)->coversCycle('monthly'))->toBeTrue();
});

test('the promotion forms set and clear the periods', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code' => 'YEARS', 'type' => 'percentage', 'value' => 60, 'billing_cycles' => ['annually', 'biennially', 'triennially'],
    ])->assertSessionHasNoErrors();
    $promo = Promotion::where('code', 'YEARS')->sole();
    expect($promo->billingCycles())->toBe(['annually', 'biennially', 'triennially']);

    $this->actingAs($admin, 'admin')->put(route('admin.config.promotions.update', $promo), [
        'code' => 'YEARS', 'type' => 'percentage', 'value' => 60,
    ])->assertSessionHasNoErrors();
    expect($promo->fresh()->billingCycles())->toBe([]);

    $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code' => 'BAD', 'type' => 'percentage', 'value' => 5, 'billing_cycles' => ['weekly'],
    ])->assertSessionHasErrors('billing_cycles.0');

    $html = $this->actingAs($admin, 'admin')->get(route('admin.config.promotions'))->assertOk()->getContent();
    expect(substr_count($html, 'name="billing_cycles[]"'))->toBe(12)
        ->and($html)->toContain(__('admin.promotions.billing_cycles'));
});
