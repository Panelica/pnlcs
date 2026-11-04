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
use App\Models\TaxRule;
use App\Services\CartService;
use App\Services\InvoiceGenerationService;
use Illuminate\Support\Facades\Mail;

/**
 * A promo code on a basket with taxed and untaxed products takes tax off its
 * share of the taxed work only, the way the group discount does. It was one
 * taxed line for the whole code, so the discount on untaxed work came off the
 * taxable amount too, down to a negative tax.
 */
function taxedShareOrder(float $taxed, float $untaxed, array $promo, bool $limitToUntaxed = false, float $groupDiscount = 0): array
{
    Mail::fake();

    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

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
        'code' => 'SHARE', 'applies_to' => $limitToUntaxed ? json_encode([$products['untaxed']->id]) : null,
        'cycles' => null, 'max_uses' => 0, 'uses' => 0,
        'start_date' => now()->subDay(), 'expiration_date' => now()->addYear(),
    ]);

    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($client->id);

    foreach ($products as $key => $product) {
        $cart->addProduct($c, $product, 'monthly', "share-{$key}.test");
    }

    $cart->applyPromoCode($c, 'SHARE');
    $quote = $cart->calculateTotal($c);
    $invoice = Invoice::with('items')->findOrFail($cart->checkout($c, $client->id, 'banktransfer')->invoice_id);

    expect((float) $invoice->total)->toEqual((float) $quote['total'])
        ->and((float) $invoice->tax)->toEqual((float) $quote['tax']);

    return [$quote, $invoice];
}

function taxedShareLines(Invoice $invoice): array
{
    return $invoice->items->where('type', 'Discount')
        ->map(fn ($line) => [(float) $line->amount, (bool) $line->taxed])->values()->all();
}

test('a percentage code on taxed and untaxed work takes tax off the taxed share only', function () {
    [, $invoice] = taxedShareOrder(100, 100, ['type' => 'percentage', 'value' => 10]);

    expect((float) $invoice->tax)->toEqual(18.0)
        ->and((float) $invoice->total)->toEqual(198.0)
        ->and(taxedShareLines($invoice))->toBe([[-10.0, true], [-10.0, false]]);
});

test('a code larger than the taxed work leaves no negative tax', function () {
    [, $invoice] = taxedShareOrder(10, 100, ['type' => 'percentage', 'value' => 50]);

    expect((float) $invoice->tax)->toEqual(1.0)
        ->and((float) $invoice->total)->toEqual(56.0)
        ->and(taxedShareLines($invoice))->toBe([[-5.0, true], [-50.0, false]]);
});

test('a fixed code is shared by net between taxed and untaxed work', function () {
    [, $invoice] = taxedShareOrder(20, 100, ['type' => 'fixed_amount', 'value' => 30]);

    expect((float) $invoice->tax)->toEqual(3.0)
        ->and((float) $invoice->total)->toEqual(93.0)
        ->and(taxedShareLines($invoice))->toBe([[-5.0, true], [-25.0, false]]);
});

test('a code limited to an untaxed product takes no tax off', function () {
    [, $invoice] = taxedShareOrder(100, 100, ['type' => 'percentage', 'value' => 10], limitToUntaxed: true);

    expect((float) $invoice->tax)->toEqual(20.0)
        ->and((float) $invoice->total)->toEqual(210.0)
        ->and(taxedShareLines($invoice))->toBe([[-10.0, false]]);
});

test('a code on top of the group discount takes tax off the taxed share only', function () {
    [, $invoice] = taxedShareOrder(100, 100, ['type' => 'percentage', 'value' => 10], groupDiscount: 10);

    expect((float) $invoice->tax)->toEqual(16.2)
        ->and((float) $invoice->total)->toEqual(178.2)
        ->and(taxedShareLines($invoice))->toBe([[-10.0, true], [-10.0, false], [-9.0, true], [-9.0, false]]);
});

test('a renewal with a recurring code takes tax off the taxed share only', function () {
    Mail::fake();

    $client = Client::factory()->create(['tax_exempt' => false]);
    TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => 20]);
    Promotion::create(['code' => 'SHARE', 'type' => 'percentage', 'value' => 50, 'recurring' => true, 'cycles' => 3, 'uses' => 0, 'max_uses' => 0]);
    $order = Order::factory()->create(['client_id' => $client->id, 'promo_code' => 'SHARE']);
    $group = ProductGroup::factory()->create();

    foreach (['taxed' => [10, true], 'untaxed' => [100, false]] as $key => [$amount, $tax]) {
        Service::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'product_id' => Product::factory()->create(['group_id' => $group->id, 'tax' => $tax])->id,
            'status' => 'active',
            'amount' => $amount,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addDays(3),
            'auto_renew' => true,
            'domain' => "share-renewal-{$key}.test",
        ]);
    }

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $invoice = Invoice::with('items')->where('client_id', $client->id)->latest('id')->first();

    expect((float) $invoice->tax)->toEqual(1.0)
        ->and((float) $invoice->total)->toEqual(56.0)
        ->and(taxedShareLines($invoice))->toBe([[-5.0, true], [-50.0, false]]);
});

test('a basket of taxed work only keeps one taxed promo line', function () {
    [, $invoice] = taxedShareOrder(100, 0, ['type' => 'percentage', 'value' => 10]);

    expect((float) $invoice->tax)->toEqual(18.0)
        ->and((float) $invoice->total)->toEqual(108.0)
        ->and(taxedShareLines($invoice))->toBe([[-10.0, true]]);
});

test('a basket of untaxed work only keeps one untaxed promo line', function () {
    [, $invoice] = taxedShareOrder(0, 100, ['type' => 'fixed_amount', 'value' => 30]);

    expect((float) $invoice->tax)->toEqual(0.0)
        ->and((float) $invoice->total)->toEqual(70.0)
        ->and(taxedShareLines($invoice))->toBe([[-30.0, false]]);
});
