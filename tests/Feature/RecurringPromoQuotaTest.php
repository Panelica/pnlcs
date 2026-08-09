<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Service;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceService;

/**
 * A recurring promotion defeated by its own sign-up quota.
 *
 * `uses` counts how many times a code has been claimed and is measured against
 * `max_uses`; `cycles` counts how many renewals a recurring code carries for a
 * given service, counted separately per service. Two counters for two different
 * questions - except that a renewal ran through the same door as a new order,
 * so every renewal spent another of the sign-ups and, once they were gone, the
 * code was refused and the discount silently disappeared from the customer's
 * invoice.
 *
 * That is the very thing the recurring feature was written to stop: a customer
 * sold "20% off, recurring for three cycles" paying full price at renewal. With
 * a quota set, it still happened - and it also ate the quota an operator had
 * set aside for new customers.
 */
function promoSoldWithQuota(string $code, int $maxUses, int $uses, bool $recurring = true, ?int $cycles = 3): Service
{
    $client = Client::factory()->create();

    Promotion::create([
        'code' => $code,
        'type' => 'percentage',
        'value' => 20,
        'recurring' => $recurring,
        'cycles' => $cycles,
        'uses' => $uses,
        'max_uses' => $maxUses,
    ]);

    $order = Order::factory()->create(['client_id' => $client->id, 'promo_code' => $code]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    return Service::factory()->create([
        'client_id' => $client->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => 100.0,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'auto_renew' => true,
        'domain' => 'promo-quota.test',
    ]);
}

function quotaRenewalFor(Service $service): ?Invoice
{
    app(InvoiceGenerationService::class)->generateDueInvoices();

    return Invoice::where('client_id', $service->client_id)->latest('id')->first();
}

function quotaDiscountOn(?Invoice $invoice): float
{
    return $invoice === null ? 0.0 : round((float) InvoiceItem::where('invoice_id', $invoice->id)
        ->where('type', 'Discount')->sum('amount'), 2);
}

it('keeps a recurring discount even when the sign-up quota is used up', function () {
    $service = promoSoldWithQuota('QUOTA20', maxUses: 1, uses: 1);

    expect(quotaDiscountOn(quotaRenewalFor($service)))->toBe(-20.00);
});

it('does not spend a sign-up on a renewal', function () {
    $service = promoSoldWithQuota('SPEND20', maxUses: 10, uses: 1);

    quotaRenewalFor($service);

    expect((int) Promotion::where('code', 'SPEND20')->first()->uses)->toBe(1);
});

it('still refuses the code on a new order once the quota is gone', function () {
    $service = promoSoldWithQuota('GONE20', maxUses: 1, uses: 1);
    $invoice = app(InvoiceService::class)->createInvoice($service->client, [
        ['description' => 'A new order', 'amount' => 100, 'qty' => 1],
    ]);

    $applied = app(InvoiceGenerationService::class)->applyPromotion($invoice->fresh(), 'GONE20');

    expect($applied)->toBeFalse()
        ->and(quotaDiscountOn($invoice->fresh()))->toBe(0.0);
});

it('still counts a new order against the quota', function () {
    $service = promoSoldWithQuota('COUNT20', maxUses: 10, uses: 1);
    $invoice = app(InvoiceService::class)->createInvoice($service->client, [
        ['description' => 'A new order', 'amount' => 100, 'qty' => 1],
    ]);

    app(InvoiceGenerationService::class)->applyPromotion($invoice->fresh(), 'COUNT20');

    expect((int) Promotion::where('code', 'COUNT20')->first()->uses)->toBe(2);
});

it('still gives no renewal discount for a code that does not recur', function () {
    $service = promoSoldWithQuota('ONEOFF20', maxUses: 10, uses: 1, recurring: false, cycles: 1);

    expect(quotaDiscountOn(quotaRenewalFor($service)))->toBe(0.0);
});
