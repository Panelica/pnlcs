<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Service;
use App\Services\InvoiceGenerationService;

/**
 * A promotion the operator marked as recurring.
 *
 * The promotion screen offers a "recurring" switch and a number of cycles, and
 * both are saved. Nothing read either: a promotion was applied to the invoice
 * the order produced and never again, so a customer sold "20% off, recurring
 * for three cycles" got the discount once and was billed the full price at
 * every renewal after that.
 */
function serviceSoldWithPromo(string $code, bool $recurring, ?int $cycles, float $amount = 100.0): Service
{
    $client = Client::factory()->create();

    Promotion::create([
        'code' => $code,
        'type' => 'percentage',
        'value' => 20,
        'recurring' => $recurring,
        'cycles' => $cycles,
        'uses' => 0,
        'max_uses' => 0,
    ]);

    $order = Order::factory()->create(['client_id' => $client->id, 'promo_code' => $code]);

    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    return Service::factory()->create([
        'client_id' => $client->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => $amount,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'auto_renew' => true,
        'domain' => 'promo-renewal.test',
    ]);
}

function renewalFor(Service $service): ?Invoice
{
    app(InvoiceGenerationService::class)->generateDueInvoices();

    return Invoice::where('client_id', $service->client_id)->latest('id')->first();
}

it('keeps giving a recurring discount at renewal', function () {
    $service = serviceSoldWithPromo('KEEP20', recurring: true, cycles: 3);

    $invoice = renewalFor($service);

    expect($invoice)->not->toBeNull()
        ->and(round((float) $invoice->credit, 2))->toBe(20.00)
        ->and(round((float) $invoice->total, 2))->toBe(80.00);
});

it('does not give a one-off discount again at renewal', function () {
    $service = serviceSoldWithPromo('ONCE20', recurring: false, cycles: 1);

    $invoice = renewalFor($service);

    expect($invoice)->not->toBeNull()
        ->and(round((float) $invoice->credit, 2))->toBe(0.00)
        ->and(round((float) $invoice->total, 2))->toBe(100.00);
});

it('stops once the promised cycles are done', function () {
    $service = serviceSoldWithPromo('TWICE20', recurring: true, cycles: 1);

    // The first renewal is the one cycle that was promised.
    $first = renewalFor($service);
    expect(round((float) $first->credit, 2))->toBe(20.00);

    $service->update(['next_due_date' => now()->addDays(3)]);
    $first->update(['status' => 'paid']);

    $second = renewalFor($service);

    expect($second->id)->not->toBe($first->id)
        ->and(round((float) $second->credit, 2))->toBe(0.00);
});
