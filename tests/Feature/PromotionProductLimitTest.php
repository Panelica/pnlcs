<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\DomainPricing;
use App\Models\InvoiceItem;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/*
 * #439. A code limited to some products discounted every one of them in the
 * basket: a "20% off your first hosting plan" welcome code took 20% off two
 * plans ordered together. A promotion can now say how many products one
 * order may have discounted; when more are eligible, the highest-priced ones
 * are. 0, as on every existing code, is no limit.
 */

function pplProduct(float $monthly): Product
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false]);
    $currency = Currency::getDefault()?->id ?? Currency::factory()->create(['is_default' => true])->id;
    Pricing::create(['type' => 'product', 'currency_id' => $currency, 'rel_id' => $product->id, 'monthly' => $monthly]);

    return $product;
}

/** @param  array<int, Product>  $products */
function pplCart(array $products): array
{
    $client = Client::factory()->create(['tax_exempt' => true]);
    $carts = app(CartService::class);
    $cart = $carts->getOrCreateCart($client->id);

    foreach ($products as $i => $product) {
        $carts->addProduct($cart, $product, 'monthly', "site{$i}.example.com");
    }

    return [$client, $carts, $cart];
}

function pplDiscountLines($order): array
{
    return InvoiceItem::where('invoice_id', $order->invoice->id)->where('type', 'Discount')->pluck('amount')->map(fn ($a) => (float) $a)->all();
}

test('without a limit every eligible product is discounted, as before', function () {
    $a = pplProduct(10);
    $b = pplProduct(30);
    Promotion::create(['code' => 'ALL20', 'type' => 'percentage', 'value' => 20]);

    [$client, $carts, $cart] = pplCart([$a, $b]);
    $carts->applyPromoCode($cart, 'ALL20');
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    expect($quoted['discount'])->toBe(8.0)
        ->and((float) $order->invoice->total)->toBe(32.0);
});

test('a limit of one discounts one product: the highest-priced, in the cart and on the invoice', function () {
    $cheap = pplProduct(10);
    $dear = pplProduct(30);
    Promotion::create(['code' => 'WELCOME20', 'type' => 'percentage', 'value' => 20, 'max_products' => 1]);

    [$client, $carts, $cart] = pplCart([$cheap, $dear]);
    expect($carts->applyPromoCode($cart, 'WELCOME20')['success'])->toBeTrue();
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    expect($quoted['discount'])->toBe(6.0)
        ->and((float) $order->invoice->total)->toBe(34.0)
        ->and(pplDiscountLines($order))->toBe([-6.0]);
});

test('the order things were added in does not change which product is discounted', function () {
    $cheap = pplProduct(10);
    $dear = pplProduct(30);
    Promotion::create(['code' => 'ONE20', 'type' => 'percentage', 'value' => 20, 'max_products' => 1]);

    [, $carts, $first] = pplCart([$dear, $cheap]);
    $carts->applyPromoCode($first, 'ONE20');
    [, $carts, $second] = pplCart([$cheap, $dear]);
    $carts->applyPromoCode($second, 'ONE20');

    expect($carts->calculateTotal($first->fresh())['discount'])->toBe(6.0)
        ->and($carts->calculateTotal($second->fresh())['discount'])->toBe(6.0);
});

test('the limit counts among the products the code covers', function () {
    $covered = pplProduct(10);
    $alsoCovered = pplProduct(20);
    $other = pplProduct(90);
    Promotion::create(['code' => 'HOST50', 'type' => 'percentage', 'value' => 50, 'max_products' => 1,
        'applies_to' => $covered->id.','.$alsoCovered->id]);

    [$client, $carts, $cart] = pplCart([$covered, $alsoCovered, $other]);
    $carts->applyPromoCode($cart, 'HOST50');
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    // Half off the 20 plan only: not the 90 one the code does not cover, not both covered ones.
    expect($quoted['discount'])->toBe(10.0)
        ->and((float) $order->invoice->total)->toBe(110.0);
});

test('with a limit a domain in the basket is not discounted, and a basket with only a domain is refused', function () {
    Mail::fake();
    DomainPricing::updateOrCreate(['extension' => '.com'], [
        'register_price' => 50, 'transfer_price' => 50, 'renew_price' => 50, 'min_years' => 1, 'max_years' => 10, 'enabled' => true,
    ]);
    Promotion::create(['code' => 'PLAN20', 'type' => 'percentage', 'value' => 20, 'max_products' => 1]);

    // The domain costs more than either plan and is still not the line chosen.
    [$client, $carts, $cart] = pplCart([pplProduct(10), pplProduct(30)]);
    $carts->addDomain($cart, 'welcome-code.com', 'register', 1);
    expect($carts->applyPromoCode($cart->fresh(), 'PLAN20')['success'])->toBeTrue();
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    expect($quoted['discount'])->toBe(6.0)
        ->and((float) $order->invoice->total)->toBe(84.0);

    [, $carts, $onlyDomain] = pplCart([]);
    $carts->addDomain($onlyDomain, 'no-plan-here.com', 'register', 1);

    expect($carts->applyPromoCode($onlyDomain->fresh(), 'PLAN20')['success'])->toBeFalse()
        ->and($carts->calculateTotal($onlyDomain->fresh())['discount'])->toBe(0.0);
});

test('a limit of two discounts the two highest-priced of three', function () {
    Promotion::create(['code' => 'TWO10', 'type' => 'percentage', 'value' => 10, 'max_products' => 2]);

    [$client, $carts, $cart] = pplCart([pplProduct(10), pplProduct(40), pplProduct(20)]);
    $carts->applyPromoCode($cart, 'TWO10');
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    expect($quoted['discount'])->toBe(6.0)
        ->and((float) $order->invoice->total)->toBe(64.0);
});

test('a fixed amount comes off the one product and no more than it costs', function () {
    Promotion::create(['code' => 'FIVE', 'type' => 'fixed_amount', 'value' => 25, 'max_products' => 1]);

    [$client, $carts, $cart] = pplCart([pplProduct(10), pplProduct(20)]);
    $carts->applyPromoCode($cart, 'FIVE');
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    // 25 off would be more than the 20 plan costs: it is free, the 10 plan is paid in full.
    expect($quoted['discount'])->toBe(20.0)
        ->and((float) $order->invoice->total)->toBe(10.0);
});

test('on a tie the earlier product is the one discounted, the same in the cart and on the invoice', function () {
    Promotion::create(['code' => 'TIE50', 'type' => 'percentage', 'value' => 50, 'max_products' => 1]);

    [$client, $carts, $cart] = pplCart([pplProduct(20), pplProduct(20)]);
    $carts->applyPromoCode($cart, 'TIE50');
    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    expect($quoted['discount'])->toBe(10.0)
        ->and((float) $order->invoice->total)->toBe(30.0);
});

test('the picker keeps the highest-priced, the earlier on a tie, and everything when there is room', function () {
    $promo = new Promotion(['max_products' => 2]);
    $price = fn (array $line) => $line['price'];
    $lines = [3 => ['price' => 5.0], 7 => ['price' => 9.0], 8 => ['price' => 9.0], 9 => ['price' => 9.0]];

    expect(array_keys($promo->pickLimited($lines, $price)))->toBe([7, 8])
        ->and(array_keys((new Promotion(['max_products' => 0]))->pickLimited($lines, $price)))->toBe([3, 7, 8, 9])
        ->and(array_keys((new Promotion(['max_products' => 9]))->pickLimited($lines, $price)))->toBe([3, 7, 8, 9])
        ->and((new Promotion)->productLimit())->toBe(0);
});

test('the promotion forms save the limit, and refuse it together with renewals', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code' => 'LIMIT1', 'type' => 'percentage', 'value' => 20, 'max_products' => 1,
    ])->assertSessionHasNoErrors();
    $promo = Promotion::where('code', 'LIMIT1')->sole();
    expect($promo->productLimit())->toBe(1);

    $this->actingAs($admin, 'admin')->put(route('admin.config.promotions.update', $promo), [
        'code' => 'LIMIT1', 'type' => 'percentage', 'value' => 20, 'max_products' => 3,
    ])->assertSessionHasNoErrors();
    expect($promo->fresh()->productLimit())->toBe(3);

    // Left out of the form: no limit.
    $this->actingAs($admin, 'admin')->put(route('admin.config.promotions.update', $promo), [
        'code' => 'LIMIT1', 'type' => 'percentage', 'value' => 20,
    ])->assertSessionHasNoErrors();
    expect($promo->fresh()->productLimit())->toBe(0);

    $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code' => 'BOTH', 'type' => 'percentage', 'value' => 20, 'max_products' => 1, 'recurring' => '1',
    ])->assertSessionHasErrors('max_products');
    expect(Promotion::where('code', 'BOTH')->exists())->toBeFalse();

    $html = $this->actingAs($admin, 'admin')->get(route('admin.config.promotions'))->assertOk()->getContent();
    expect($html)->toContain(__('admin.promotions.max_products'))->toContain('name="max_products"');
});
