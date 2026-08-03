<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Promotion;
use App\Services\InvoiceGenerationService;

/**
 * Amounts written into text that is stored and read later.
 *
 * The screens were taught the shop currency; eight places in the code still
 * wrote a dollar sign into text that goes on to be saved and shown: the note
 * on an invoice when a promotion is applied, the description of a disk or
 * bandwidth overage line - which the customer reads on their invoice - and the
 * descriptions of affiliate payouts and withdrawals.
 */
beforeEach(function () {
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(
        ['code' => 'TRY'],
        ['prefix' => '₺', 'suffix' => '', 'rate' => 1, 'is_default' => true]
    );
    app()->forgetInstance('pnlcs.currency');
});

test('the note left by a promotion is in the shop currency', function () {
    $client = Client::factory()->create(['country' => 'TR', 'tax_exempt' => true]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => 100,
        'total' => 100,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => 100,
        'taxed' => false,
    ]);

    Promotion::create([
        'code' => 'TENOFF',
        'type' => 'percentage',
        'value' => 10,
        'recurring' => false,
    ]);

    app(InvoiceGenerationService::class)->applyPromotion($invoice->fresh('items'), 'TENOFF');

    expect($invoice->fresh()->notes)->toContain('₺')
        ->and($invoice->fresh()->notes)->not->toContain('$');
});

test('the helper answers with the sign in force', function () {
    expect(currency_symbol())->toBe('₺');
});
