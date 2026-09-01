<?php

use App\Models\Client;
use App\Models\TaxRule;
use App\Services\InvoiceService;

/**
 * The tax form says a blank country means "all countries" - the list even
 * renders it as "All" - but the matcher only ever looked for the client's
 * exact country code. A catch-all VAT rule matched nobody, and every invoice
 * quietly went out untaxed.
 */
it('applies a blank-country rule to every client, as the form promises', function () {
    TaxRule::create(['name' => 'VAT', 'tax_rate' => 20, 'country' => '', 'state' => '', 'is_default' => true]);
    $client = Client::factory()->create(['country' => 'TR', 'tax_exempt' => false]);

    $tax = app(InvoiceService::class)->calculateTax(100, $client->id);

    expect($tax['tax_rate'])->toBe(20.0)->and($tax['tax'])->toBe(20.0);
});

it('lets a country-specific rule outrank the catch-all', function () {
    TaxRule::create(['name' => 'VAT', 'tax_rate' => 20, 'country' => '', 'state' => '', 'is_default' => true]);
    TaxRule::create(['name' => 'KDV', 'tax_rate' => 10, 'country' => 'TR', 'state' => '']);
    $client = Client::factory()->create(['country' => 'TR', 'tax_exempt' => false]);

    expect(app(InvoiceService::class)->calculateTax(100, $client->id)['tax_rate'])->toBe(10.0);
});

it('still matches an exact-country rule as before', function () {
    TaxRule::create(['name' => 'KDV', 'tax_rate' => 18, 'country' => 'TR', 'state' => '']);
    $client = Client::factory()->create(['country' => 'TR', 'tax_exempt' => false]);
    $other = Client::factory()->create(['country' => 'DE', 'tax_exempt' => false]);

    expect(app(InvoiceService::class)->calculateTax(100, $client->id)['tax_rate'])->toBe(18.0)
        ->and(app(InvoiceService::class)->calculateTax(100, $other->id)['tax_rate'])->toBe(0.0);
});
