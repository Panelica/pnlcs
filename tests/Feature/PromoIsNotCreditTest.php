<?php

use App\Models\Client;
use App\Models\InvoiceItem;
use App\Models\Promotion;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceService;

/**
 * A discount is not the customer's money.
 *
 * A promotion was written into the invoice's credit column - the same column
 * that holds account balance the customer actually paid in. Two different
 * things in one field: money they own, and money they were never charged.
 *
 * Cancelling an invoice hands the balance back, and it cannot tell the two
 * apart, so a cancelled order with a ten pound discount handed the customer
 * ten pounds of real credit they had never paid. The invoice also showed the
 * discount to the customer as "Credit applied" rather than as the promotion
 * they used.
 */
function invoiceWithPromo(string $code, float $amount, float $percent, float $startingCredit = 0.0): array
{
    $client = Client::factory()->create(['credit' => $startingCredit]);

    $promo = Promotion::factory()->create([
        'code' => $code,
        'type' => 'percentage',
        'value' => $percent,
        'max_uses' => 0,
        'uses' => 0,
        'applies_to' => '',
        'start_date' => null,
        'expiration_date' => null,
        'apply_once' => false,
        'new_signups_only' => false,
        'existing_client' => false,
    ]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [[
        'type' => 'Hosting', 'description' => 'Hosting', 'amount' => $amount, 'taxed' => false,
    ]]);

    app(InvoiceGenerationService::class)->applyPromotion($invoice->fresh(), $promo->code);

    return [$client->fresh(), $invoice->fresh(), $promo->fresh()];
}

it('does not turn a discount into account balance when the invoice is cancelled', function () {
    [$client, $invoice] = invoiceWithPromo('CANCELME', 100.00, 10.0);

    expect(round((float) $invoice->total, 2))->toBe(90.00);

    app(InvoiceService::class)->cancelInvoice($invoice);

    expect(round((float) $client->fresh()->credit, 2))->toBe(0.00);
});

it('shows the discount as the promotion, not as credit', function () {
    [$client, $invoice, $promo] = invoiceWithPromo('SHOWME', 100.00, 10.0);

    expect(round((float) $invoice->credit, 2))->toBe(0.00);

    $discounts = InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Discount')->get();

    expect(round((float) $discounts->sum('amount'), 2))->toBe(-10.00);
    expect($discounts->first()->description)->toContain('SHOWME');
});

it('still gives back balance the customer actually paid in', function () {
    [$client, $invoice] = invoiceWithPromo('BOTH', 100.00, 10.0, startingCredit: 30.00);

    // 100 less a 10 discount, less 30 of balance.
    expect(round((float) $invoice->total, 2))->toBe(60.00);
    expect(round((float) $client->credit, 2))->toBe(0.00);

    app(InvoiceService::class)->cancelInvoice($invoice);

    // The 30 comes back. The 10 was never theirs.
    expect(round((float) $client->fresh()->credit, 2))->toBe(30.00);
});
