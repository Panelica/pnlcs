<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\TaxRule;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/**
 * What the cart shows has to be what the invoice charges.
 */
test('a promotion with tax charges what the cart quoted', function () {
    Mail::fake();

    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => false]);
    TaxRule::create(['level' => 1, 'name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 10]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => true,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 100]
    );

    Promotion::create([
        'code' => 'TENOFF', 'type' => 'percentage', 'value' => 10,
        'applies_to' => null, 'cycles' => null, 'max_uses' => 0, 'uses' => 0,
        'start_date' => now()->subDay(), 'expiration_date' => now()->addYear(),
    ]);

    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($client->id);
    $cart->addProduct($c, $product, 'monthly', 'promo-tax.com');
    $cart->applyPromoCode($c, 'TENOFF');

    $quoted = $cart->calculateTotal($c)['total'];

    $order = $cart->checkout($c, $client->id, 'banktransfer');
    $charged = (float) Invoice::findOrFail($order->invoice_id)->total;

    // 100 less 10 percent, plus 10 percent tax on the 90 that is actually paid.
    expect($charged)->toBe((float) $quoted)
        ->and($charged)->toBe(99.0)
        ->and((float) Invoice::findOrFail($order->invoice_id)->tax)->toBe(9.0);
});

test('a tax-exempt customer is not quoted tax either', function () {
    Mail::fake();
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => true]);
    TaxRule::create(['level' => 1, 'name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 20]);

    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => true]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 50]
    );

    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($client->id);
    $cart->addProduct($c, $product, 'monthly', 'exempt.com');

    // The cart used to read the first level-1 rule in the table and taxed an
    // exempt customer on the page, then the invoice charged them nothing.
    expect((float) $cart->calculateTotal($c)['total'])->toBe(50.0);

    $order = $cart->checkout($c, $client->id, 'banktransfer');
    expect((float) Invoice::findOrFail($order->invoice_id)->total)->toBe(50.0);
});

test('a rule for another country is not applied', function () {
    Mail::fake();
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => false, 'country' => 'TR']);
    TaxRule::create(['level' => 1, 'name' => 'Elsewhere', 'country' => 'DE', 'state' => '', 'tax_rate' => 19]);

    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => true]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 50]
    );

    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($client->id);
    $cart->addProduct($c, $product, 'monthly', 'other-country.com');

    expect((float) $cart->calculateTotal($c)['total'])->toBe(50.0);
});
