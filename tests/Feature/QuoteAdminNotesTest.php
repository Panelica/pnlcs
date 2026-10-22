<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Mail;

/**
 * The quote form labels `notes` "Admin Notes", but converting the quote copied
 * them into the invoice notes, which the customer's invoice PDF prints.
 */
it('does not carry the admin notes of a quote onto its invoice', function () {
    Mail::fake();
    Currency::where('is_default', true)->exists()
        || Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $client = Client::factory()->create(['tax_exempt' => true, 'credit' => 0]);
    $quote = Quote::factory()->sent()->create([
        'client_id' => $client->id, 'subtotal' => 100, 'tax' => 0, 'total' => 100,
        'notes' => 'Internal: margin is thin, do not discount further',
    ]);
    QuoteItem::create(['quote_id' => $quote->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'taxable' => false]);

    $invoice = app(QuoteService::class)->convertToInvoice($quote);

    expect($invoice->fresh()->notes)->not->toContain('Internal')
        ->and($invoice->fresh()->notes)->toBe("Quote #{$quote->id}");

});
