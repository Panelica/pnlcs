<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Services\InvoiceGenerationService;

/**
 * The rules attached to a promotion, on every path that applies one.
 *
 * A promotion can be limited to one per customer, to customers who have never
 * ordered, to customers who have, or to particular products. The cart checks
 * all of that. The invoice side checked only whether the code itself was still
 * alive - in date, and not past its total number of uses - and applied the
 * discount to anyone.
 *
 * The order endpoint hands its promocode straight through to that side, so a
 * once-per-customer code could be spent again and again by the same customer,
 * and a new-signups code could be spent by somebody who had been ordering for
 * years.
 */
function promotionInvoice(Client $client, float $total = 100.0): Invoice
{
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => $total,
        'total' => $total,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => $total,
        'taxed' => false,
    ]);

    return $invoice->fresh();
}

it('refuses a once-per-customer code the customer has already spent', function () {
    $client = Client::factory()->create();

    $promo = Promotion::create([
        'code' => 'ONCEONLY',
        'type' => 'percentage',
        'value' => 10,
        'apply_once' => true,
        'uses' => 0,
        'max_uses' => 0,
    ]);

    // They have had it before.
    Order::factory()->create(['client_id' => $client->id, 'promo_code' => 'ONCEONLY']);

    $applied = app(InvoiceGenerationService::class)->applyPromotion(promotionInvoice($client), 'ONCEONLY');

    expect($applied)->toBeFalse()
        ->and($promo->fresh()->uses)->toBe(0);
});

it('refuses a new-signup code to a customer who has ordered', function () {
    $client = Client::factory()->create();

    Promotion::create([
        'code' => 'FIRSTORDER',
        'type' => 'percentage',
        'value' => 10,
        'new_signups_only' => true,
        'uses' => 0,
        'max_uses' => 0,
    ]);

    Order::factory()->create(['client_id' => $client->id, 'promo_code' => 'SOMETHINGELSE']);

    $applied = app(InvoiceGenerationService::class)->applyPromotion(promotionInvoice($client), 'FIRSTORDER');

    expect($applied)->toBeFalse();
});

it('still applies an ordinary code', function () {
    $client = Client::factory()->create();

    $promo = Promotion::create([
        'code' => 'PLAIN10',
        'type' => 'percentage',
        'value' => 10,
        'uses' => 0,
        'max_uses' => 0,
    ]);

    $invoice = promotionInvoice($client);

    expect(app(InvoiceGenerationService::class)->applyPromotion($invoice, 'PLAIN10'))->toBeTrue()
        ->and($promo->fresh()->uses)->toBe(1)
        ->and(round((float) $invoice->fresh()->total, 2))->toBe(90.00);
});
