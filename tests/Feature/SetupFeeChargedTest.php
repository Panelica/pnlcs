<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\TaxRule;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/**
 * The setup fee the product form saves is charged on the first invoice.
 *
 * Every product has a setup fee per billing cycle in its pricing row, and the
 * admin form has always saved it - but nothing read it. A plan priced at "100 a
 * month plus 50 setup" was quoted and billed 100. The fee is now on the basket,
 * on the order's invoice as a line of its own, taxed as its product is, and it
 * never comes back on a renewal.
 */
function setupFeeOrder(array $pricing, array $productAttrs = [], string $cycle = 'monthly'): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => false]);
    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 10]);

    $product = Product::factory()->create($productAttrs + [
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => true,
        'auto_setup' => 'payment',
        'server_type' => null,
    ]);

    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        $pricing
    );

    $cartService = app(CartService::class);
    $cart = $cartService->getOrCreateCart($client->id);
    $cartService->addProduct($cart, $product->fresh(), $cycle, 'setup-fee.com');

    $quoted = $cartService->calculateTotal($cart->fresh());
    $order = $cartService->checkout($cart->fresh(), $client->id, 'banktransfer');

    return [$quoted, Invoice::with('items')->find($order->invoice_id), Service::where('order_id', $order->id)->first(), $order];
}

it('charges the setup fee on the first invoice and quotes it in the basket', function () {
    Mail::fake();
    [$quoted, $invoice, $service] = setupFeeOrder(['monthly' => 100, 'monthly_setup' => 50]);

    expect((float) $quoted['items'][0]['setup_fee'])->toBe(50.0);
    // 100 + 50 setup, plus 10 percent tax on both.
    expect(round((float) $quoted['total'], 2))->toBe(165.0);
    expect(round((float) $invoice->total, 2))->toBe(165.0);

    $setup = $invoice->items->firstWhere('type', 'Setup');
    expect($setup)->not->toBeNull();
    expect((float) $setup->amount)->toBe(50.0);
    expect((int) $setup->rel_id)->toBe($service->id);
    expect((bool) $setup->taxed)->toBeTrue();
});

it('bills renewals at the price alone', function () {
    Mail::fake();
    [, , $service, $order] = setupFeeOrder(['monthly' => 100, 'monthly_setup' => 50]);

    // The first payment includes the fee; the recurring amount does not.
    expect((float) $service->first_payment_amount)->toBe(150.0);
    expect((float) $service->amount)->toBe(100.0);
    expect((float) $order->amount)->toBe(150.0);
});

it('takes the fee of the cycle that was ordered', function () {
    Mail::fake();
    [$quoted, $invoice] = setupFeeOrder(['monthly' => 10, 'monthly_setup' => 5, 'annually' => 100, 'annually_setup' => 0], cycle: 'annually');

    expect((float) $quoted['items'][0]['setup_fee'])->toBe(0.0);
    expect($invoice->items->firstWhere('type', 'Setup'))->toBeNull();
});

it('does not tax the fee of an untaxed product', function () {
    Mail::fake();
    [$quoted, $invoice] = setupFeeOrder(['monthly' => 100, 'monthly_setup' => 50], ['tax' => false]);

    expect(round((float) $quoted['total'], 2))->toBe(150.0);
    expect(round((float) $invoice->total, 2))->toBe(150.0);
    expect((bool) $invoice->items->firstWhere('type', 'Setup')->taxed)->toBeFalse();
});

it('never charges a setup fee on a free product', function () {
    Mail::fake();
    [$quoted, $invoice] = setupFeeOrder(['monthly' => 0, 'monthly_setup' => 50], ['pay_type' => 'free']);

    expect((float) $quoted['items'][0]['setup_fee'])->toBe(0.0);
    expect($invoice->items->firstWhere('type', 'Setup'))->toBeNull();
});

it('saves the two- and three-year setup fees from the product form', function () {
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    expect($product->setupFeeFor('biennially'))->toBe(0.0);

    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['biennially' => 180, 'biennially_setup' => 25, 'triennially' => 250, 'triennially_setup' => 0]
    );

    expect($product->fresh()->setupFeeFor('biennially'))->toBe(25.0);
    expect($product->fresh()->setupFeeFor('triennially'))->toBe(0.0);
});
