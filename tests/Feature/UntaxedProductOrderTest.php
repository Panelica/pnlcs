<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\TaxRule;
use App\Services\InvoiceGenerationService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Mail;

/**
 * A product marked "not taxable", taxed once.
 *
 * The order path writes 'taxed' => true onto the hosting line whatever the
 * product says. Every other path asks the product: the renewal generator, the
 * upgrade charge and the addon line all read product->tax.
 *
 * So a product the operator marked as not taxable is taxed on the invoice the
 * customer pays at sign-up, and never again on any renewal. The customer is
 * overcharged once and the books show tax collected on something that does not
 * carry it.
 */
function untaxedProductOrder(bool $taxable): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => false]);
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

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service',
        'product_id' => $product->id,
        'domain' => 'taxcheck.com',
        'amount' => 100.00,
        'billing_cycle' => 'Monthly',
    ]], 'banktransfer');

    return [$client, $product, Invoice::find($order->invoice_id)];
}

it('does not tax a product the operator marked as not taxable', function () {
    Mail::fake();
    [$client, $product, $invoice] = untaxedProductOrder(taxable: false);

    expect((float) $invoice->tax)->toBe(0.0);
    expect((float) $invoice->total)->toBe(100.0);
});

it('still taxes one that is', function () {
    Mail::fake();
    [$client, $product, $invoice] = untaxedProductOrder(taxable: true);

    expect((float) $invoice->tax)->toBe(10.0);
    expect((float) $invoice->total)->toBe(110.0);
});

it('agrees with what the renewal of the same product charges', function () {
    Mail::fake();
    [$client, $product, $invoice] = untaxedProductOrder(taxable: false);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'billing_cycle' => 'Monthly',
        'amount' => 100,
        'auto_renew' => true,
        'next_due_date' => now()->addDay(),
        'domain' => 'renewal-taxcheck.com',
    ]);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $renewal = Invoice::whereHas('items', fn ($q) => $q->where('type', 'Hosting')->where('rel_id', $service->id))->firstOrFail();

    // The sign-up invoice and the renewal must treat the same product the same
    // way; the renewal has always read the product's own flag.
    expect((float) $renewal->tax)->toBe(0.0);
    expect((float) $invoice->tax)->toBe((float) $renewal->tax);
});
