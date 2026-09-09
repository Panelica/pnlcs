<?php

use App\Models\Client;
use App\Models\TaxRule;
use App\Services\QuoteService;

/*
 * A quote is a promise of what the invoice will say. It used to work out tax
 * from the installation's default rule while the invoice reads the customer's
 * own country rule, so a customer quoted one total was billed another.
 */
test('a quote carries the tax the customer will actually be charged', function () {
    TaxRule::create(['name' => 'Global', 'country' => '', 'state' => '', 'tax_rate' => 0, 'is_default' => true]);
    TaxRule::create(['name' => 'KDV', 'country' => 'TR', 'state' => '', 'tax_rate' => 20, 'is_default' => false]);
    $client = Client::factory()->create(['country' => 'TR', 'state' => '', 'tax_exempt' => false]);

    $quote = app(QuoteService::class)->createQuote($client, [
        'subject' => 'Hosting', 'valid_until' => now()->addDays(14)->toDateString(),
        'items' => [['description' => 'Plan', 'quantity' => 1, 'unit_price' => 100, 'taxable' => true]],
    ]);

    $invoice = app(QuoteService::class)->convertToInvoice($quote);

    expect((float) $quote->tax)->toBe(20.0)
        ->and((float) $quote->total)->toBe(120.0)
        ->and((float) $invoice->total)->toBe((float) $quote->total);
});

test('a tax-exempt customer is quoted no tax', function () {
    TaxRule::create(['name' => 'KDV', 'country' => 'TR', 'state' => '', 'tax_rate' => 20, 'is_default' => true]);
    $client = Client::factory()->create(['country' => 'TR', 'state' => '', 'tax_exempt' => true]);

    $quote = app(QuoteService::class)->createQuote($client, [
        'subject' => 'Hosting', 'valid_until' => now()->addDays(14)->toDateString(),
        'items' => [['description' => 'Plan', 'quantity' => 1, 'unit_price' => 100, 'taxable' => true]],
    ]);

    expect((float) $quote->tax)->toBe(0.0);
});
