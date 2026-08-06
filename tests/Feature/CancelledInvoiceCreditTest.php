<?php

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Services\InvoiceService;

/**
 * Credit spent on an invoice that is then cancelled.
 *
 * Applying credit writes it onto the invoice, takes the invoice total down by
 * the same amount, and takes the balance off the customer. It does not record
 * a transaction, because no money moved.
 *
 * Cancelling an invoice hands its payments back as credit, and it works out
 * what to hand back from the transactions. For an invoice part-settled from
 * the balance there are none, so nothing goes back: the customer's credit was
 * taken, the invoice it was taken for was cancelled, and the money is gone.
 */
function creditedInvoice(float $credit, float $amount): array
{
    $client = Client::factory()->create(['credit' => $credit]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [[
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => $amount,
        'taxed' => false,
    ]]);

    return [$client->fresh(), $invoice->fresh()];
}

it('gives the credit back when the invoice it part-paid is cancelled', function () {
    [$client, $invoice] = creditedInvoice(30.00, 100.00);

    // The balance went onto the invoice, not into a transaction.
    expect((float) $invoice->credit)->toBe(30.00);
    expect((float) $client->credit)->toBe(0.00);

    app(InvoiceService::class)->cancelInvoice($invoice);

    expect((float) $client->fresh()->credit)->toBe(30.00);
    expect(Credit::where('client_id', $client->id)->where('amount', 30.00)->exists())->toBeTrue();
});

it('does not hand the same credit back twice', function () {
    [$client, $invoice] = creditedInvoice(30.00, 100.00);

    app(InvoiceService::class)->cancelInvoice($invoice);
    app(InvoiceService::class)->cancelInvoice($invoice->fresh());

    expect((float) $client->fresh()->credit)->toBe(30.00);
});

it('invents no credit for an invoice that never had any', function () {
    $client = Client::factory()->create(['credit' => 0]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [[
        'type' => 'Hosting', 'description' => 'Hosting', 'amount' => 50.00, 'taxed' => false,
    ]]);

    app(InvoiceService::class)->cancelInvoice($invoice);

    expect((float) $client->fresh()->credit)->toBe(0.00);
});

it('leaves an invoice fully settled from credit alone', function () {
    // Fully covered means it was marked paid, and a paid invoice is refunded
    // rather than cancelled - the balance must not come back on top of that.
    [$client, $invoice] = creditedInvoice(100.00, 40.00);

    expect(strtolower((string) $invoice->fresh()->status))->toBe('paid');

    app(InvoiceService::class)->cancelInvoice($invoice->fresh());

    expect((float) $client->fresh()->credit)->toBe(60.00);
    expect(strtolower((string) Invoice::find($invoice->id)->status))->toBe('paid');
});
