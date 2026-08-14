<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Services\InvoiceService;

/*
 * An invoice already froze its money. It did not freeze its buyer, so a customer
 * who moved house rewrote the address on every invoice they had ever been issued
 * — including ones already filed with an accountant. For VAT the buyer address
 * and tax id on the document must be the ones that applied on the invoice date.
 *
 * These pin: the snapshot is taken at issue time, later edits cannot reach an
 * issued invoice, and invoices predating the snapshot still render (fallback),
 * because nothing was backfilled.
 */

it('freezes the buyer when the invoice is issued', function () {
    $client = Client::factory()->create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'company_name' => 'Analytical Engines Ltd',
        'address1' => '12 Dorset Street', 'city' => 'London',
        'postcode' => 'W1U 6QJ', 'country' => 'GB', 'tax_id' => 'GB123456789',
    ]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ]);

    expect($invoice->buyer_first_name)->toBe('Ada')
        ->and($invoice->buyer_company_name)->toBe('Analytical Engines Ltd')
        ->and($invoice->buyer_address1)->toBe('12 Dorset Street')
        ->and($invoice->buyer_tax_id)->toBe('GB123456789')
        ->and($invoice->buyer_country)->toBe('GB');
});

it('does not let a later customer edit rewrite an issued invoice', function () {
    $client = Client::factory()->create([
        'first_name' => 'Ada', 'address1' => '12 Dorset Street',
        'city' => 'London', 'tax_id' => 'GB123456789',
    ]);
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ]);

    // The customer moves and re-registers for VAT elsewhere.
    $client->update([
        'address1' => '99 Rue de Rivoli', 'city' => 'Paris',
        'country' => 'FR', 'tax_id' => 'FR987654321',
    ]);

    $invoice->refresh()->load('client');

    // The document still reads as it did on the day it was issued.
    expect($invoice->buyer('address1'))->toBe('12 Dorset Street')
        ->and($invoice->buyer('city'))->toBe('London')
        ->and($invoice->buyer('tax_id'))->toBe('GB123456789')
        // ...while the client record itself has of course moved on.
        ->and($invoice->client->address1)->toBe('99 Rue de Rivoli');
});

it('still renders invoices issued before snapshots existed', function () {
    // Nothing was backfilled: an older invoice has NULL buyer columns and must
    // keep rendering from the live client record exactly as it does today.
    $client = Client::factory()->create(['first_name' => 'Grace', 'city' => 'Arlington']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'buyer_first_name' => null, 'buyer_city' => null, 'buyer_tax_id' => null,
    ]);

    $invoice->load('client');

    expect($invoice->buyer('first_name'))->toBe('Grace')
        ->and($invoice->buyer('city'))->toBe('Arlington')
        ->and($invoice->buyer('tax_id'))->toBeNull();
});

it('records whether the buyer was tax exempt at issue time', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ]);

    expect((bool) $invoice->buyer_tax_exempt)->toBeTrue();

    // Losing the exemption later must not change how the issued invoice reads.
    $client->update(['tax_exempt' => false]);
    expect((bool) $invoice->refresh()->buyer_tax_exempt)->toBeTrue();
});
