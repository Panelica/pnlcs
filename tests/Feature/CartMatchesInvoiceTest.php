<?php

use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\TaxRule;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/**
 * What the basket quotes is what the invoice charges.
 *
 * The basket applies the tax rate to the whole subtotal and knows nothing
 * about which lines carry tax, and it never shows the customer's group
 * discount at all. The invoice does both: tax lands only on taxable lines, and
 * the group discount is a line of its own.
 *
 * So a customer buying something the operator marked as not taxable is quoted
 * tax that is never charged, and a customer in a discount group is quoted the
 * full price and billed less. Either way the figure they approve is not the
 * figure on the invoice.
 */
function cartVsInvoice(bool $taxable, float $groupDiscount = 0.0): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => false]);

    if ($groupDiscount > 0) {
        $group = ClientGroup::create(['name' => 'Resellers', 'discount_percent' => $groupDiscount]);
        $client->update(['group_id' => $group->id]);
    }

    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 10]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => $taxable,
        'auto_setup' => 'payment',
        'server_type' => null,
    ]);

    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 100]
    );

    $cartService = app(CartService::class);
    $cart = $cartService->getOrCreateCart($client->id);
    $cartService->addProduct($cart, $product, 'monthly', 'quoted-vs-billed.com');

    $quoted = $cartService->calculateTotal($cart->fresh());
    $order = $cartService->checkout($cart->fresh(), $client->id, 'banktransfer');
    $invoice = Invoice::find($order->invoice_id);

    return [round((float) $quoted['total'], 2), round((float) $invoice->total, 2)];
}

it('quotes no tax on something that is not taxed', function () {
    Mail::fake();
    [$quoted, $billed] = cartVsInvoice(taxable: false);

    expect($quoted)->toBe(100.0);
    expect($quoted)->toBe($billed);
});

it('quotes the group discount the invoice gives', function () {
    Mail::fake();
    [$quoted, $billed] = cartVsInvoice(taxable: true, groupDiscount: 10.0);

    // 100 less 10 percent, plus 10 percent tax on what is left.
    expect($quoted)->toBe(99.0);
    expect($quoted)->toBe($billed);
});

it('still agrees on a plain taxable order', function () {
    Mail::fake();
    [$quoted, $billed] = cartVsInvoice(taxable: true);

    expect($quoted)->toBe(110.0);
    expect($quoted)->toBe($billed);
});

it('agrees when both apply at once', function () {
    Mail::fake();
    [$quoted, $billed] = cartVsInvoice(taxable: false, groupDiscount: 10.0);

    expect($quoted)->toBe(90.0);
    expect($quoted)->toBe($billed);
});
