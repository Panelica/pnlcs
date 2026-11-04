<?php

use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Services\CartService;
use App\Services\InvoiceGenerationService;
use Illuminate\Support\Facades\Mail;

/**
 * With prices that include tax, a percentage code comes off the price the
 * customer sees, as the cart quotes it. The invoice took it off the subtotal,
 * which in that mode has the tax taken out: 10% of a 120 basket was quoted
 * 108 and invoiced 110.
 */
beforeEach(fn () => Mail::fake());

function inclusivePromoOrder(float $taxed, float $untaxed, array $promo, bool $limitToTaxed = false, float $groupDiscount = 0, string $inclusive = '1'): array
{
    Setting::set('TaxInclusivePrices', $inclusive);

    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'EUR', 'prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => false] + ($groupDiscount > 0
        ? ['group_id' => ClientGroup::create(['name' => 'Resellers', 'discount_percent' => $groupDiscount])->id]
        : []));
    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 20]);

    $group = ProductGroup::factory()->create();
    $products = [];

    foreach (['taxed' => [$taxed, true], 'untaxed' => [$untaxed, false]] as $key => [$price, $tax]) {
        if ($price <= 0) {
            continue;
        }

        $products[$key] = Product::factory()->create(['group_id' => $group->id, 'tax' => $tax]);
        Pricing::updateOrCreate(
            ['type' => 'product', 'rel_id' => $products[$key]->id, 'currency_id' => $currency->id],
            ['monthly' => $price]
        );
    }

    Promotion::create($promo + [
        'code' => 'GROSS', 'applies_to' => $limitToTaxed ? json_encode([$products['taxed']->id]) : null,
        'cycles' => null, 'max_uses' => 0, 'uses' => 0,
        'start_date' => now()->subDay(), 'expiration_date' => now()->addYear(),
    ]);

    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($client->id);

    foreach ($products as $key => $product) {
        $cart->addProduct($c, $product, 'monthly', "gross-{$key}.test");
    }

    $cart->applyPromoCode($c, 'GROSS');
    $quote = $cart->calculateTotal($c);
    $invoice = Invoice::with('items')->findOrFail($cart->checkout($c, $client->id, 'banktransfer')->invoice_id);

    expect((float) $invoice->total)->toEqual((float) $quote['total'])
        ->and((float) $invoice->tax)->toEqual((float) $quote['tax']);

    return [$quote, $invoice];
}

function inclusivePromoLines(Invoice $invoice): array
{
    return $invoice->items->where('type', 'Discount')
        ->map(fn ($line) => [(float) $line->amount, (bool) $line->taxed])->values()->all();
}

test('with prices that include tax a percentage code comes off the price shown', function () {
    [, $invoice] = inclusivePromoOrder(120, 0, ['type' => 'percentage', 'value' => 10]);

    expect((float) $invoice->total)->toEqual(108.0)
        ->and((float) $invoice->tax)->toEqual(18.0)
        ->and((float) $invoice->subtotal)->toEqual(90.0)
        ->and(inclusivePromoLines($invoice))->toBe([[-12.0, true]]);
});

test('with prices that include tax a code on taxed and untaxed work comes off both prices shown', function () {
    [, $invoice] = inclusivePromoOrder(12, 120, ['type' => 'percentage', 'value' => 50]);

    expect((float) $invoice->total)->toEqual(66.0)
        ->and((float) $invoice->tax)->toEqual(1.0)
        ->and(inclusivePromoLines($invoice))->toBe([[-6.0, true], [-60.0, false]]);
});

test('with prices that include tax a 100% code on a product leaves nothing to pay', function () {
    [, $invoice] = inclusivePromoOrder(120, 0, ['type' => 'percentage', 'value' => 100], limitToTaxed: true);

    expect((float) $invoice->total)->toEqual(0.0)
        ->and(inclusivePromoLines($invoice))->toBe([[-120.0, true]]);
});

test('with prices that include tax a code on top of the group discount comes off what is left', function () {
    [, $invoice] = inclusivePromoOrder(120, 0, ['type' => 'percentage', 'value' => 10], groupDiscount: 10);

    expect((float) $invoice->total)->toEqual(97.2)
        ->and((float) $invoice->tax)->toEqual(16.2)
        ->and(inclusivePromoLines($invoice))->toBe([[-12.0, true], [-10.8, true]]);
});

test('with prices that include tax a renewal with a recurring code comes off the price', function () {
    Setting::set('TaxInclusivePrices', '1');

    $client = Client::factory()->create(['tax_exempt' => false]);
    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 20]);
    Promotion::create(['code' => 'GROSS', 'type' => 'percentage', 'value' => 10, 'recurring' => true, 'cycles' => 3, 'uses' => 0, 'max_uses' => 0]);
    $order = Order::factory()->create(['client_id' => $client->id, 'promo_code' => 'GROSS']);

    Service::factory()->create([
        'client_id' => $client->id,
        'order_id' => $order->id,
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => true])->id,
        'status' => 'active',
        'amount' => 120,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'auto_renew' => true,
        'domain' => 'gross-renewal.test',
    ]);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $invoice = Invoice::with('items')->where('client_id', $client->id)->latest('id')->first();

    expect((float) $invoice->total)->toEqual(108.0)
        ->and((float) $invoice->tax)->toEqual(18.0)
        ->and(inclusivePromoLines($invoice))->toBe([[-12.0, true]]);
});

test('with prices that include tax a fixed code was already right', function () {
    [, $invoice] = inclusivePromoOrder(120, 0, ['type' => 'fixed_amount', 'value' => 30]);

    expect((float) $invoice->total)->toEqual(90.0)
        ->and((float) $invoice->tax)->toEqual(15.0)
        ->and(inclusivePromoLines($invoice))->toBe([[-30.0, true]]);
});

test('with tax added on top a percentage code is unchanged', function () {
    [, $invoice] = inclusivePromoOrder(120, 0, ['type' => 'percentage', 'value' => 10], inclusive: '0');

    expect((float) $invoice->total)->toEqual(129.6)
        ->and((float) $invoice->tax)->toEqual(21.6)
        ->and((float) $invoice->subtotal)->toEqual(108.0)
        ->and(inclusivePromoLines($invoice))->toBe([[-12.0, true]]);
});
