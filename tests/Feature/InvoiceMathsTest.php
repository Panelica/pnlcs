<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRule;
use App\Services\InvoiceService;

// ---------------------------------------------------------------------------
// Quantity
// ---------------------------------------------------------------------------

test('quantity multiplies the line amount when calculating the subtotal', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $service = app(InvoiceService::class);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 100, 'qty' => 3, 'taxed' => false]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 50, 'qty' => 2, 'taxed' => false]);

    $updated = $service->recalculateTotals($invoice);

    expect((float) $updated->subtotal)->toBe(400.00)
        ->and((float) $updated->total)->toBe(400.00);
});

// ---------------------------------------------------------------------------
// Per-item VAT rate
// ---------------------------------------------------------------------------

test('a per-item VAT rate is applied to the quantity-adjusted amount', function () {
    $client = Client::factory()->create(['tax_exempt' => false]);
    $service = app(InvoiceService::class);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0, 'tax_rate' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 100, 'qty' => 2, 'tax_rate' => 23]);

    $updated = $service->recalculateTotals($invoice);

    expect((float) $updated->subtotal)->toBe(200.00)
        ->and((float) $updated->tax)->toBe(46.00)
        ->and((float) $updated->total)->toBe(246.00);
});

test('mixed rates and quantities are summed line by line', function () {
    $client = Client::factory()->create(['tax_exempt' => false]);
    $service = app(InvoiceService::class);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0, 'tax_rate' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 100, 'qty' => 3, 'tax_rate' => 23]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 50, 'qty' => 2, 'tax_rate' => 8]);

    $updated = $service->recalculateTotals($invoice);

    // 3 x 100 @ 23% (tax 69.00) + 2 x 50 @ 8% (tax 8.00)
    expect((float) $updated->subtotal)->toBe(400.00)
        ->and((float) $updated->tax)->toBe(77.00)
        ->and((float) $updated->total)->toBe(477.00);
});

test('a single rate across the invoice is recorded back on the invoice', function () {
    $client = Client::factory()->create(['tax_exempt' => false]);
    $service = app(InvoiceService::class);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0, 'tax_rate' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 100, 'qty' => 1, 'tax_rate' => 23]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 10, 'qty' => 1, 'tax_rate' => 23]);

    $updated = $service->recalculateTotals($invoice);

    expect((float) $updated->subtotal)->toBe(110.00)
        ->and((float) $updated->tax)->toBe(25.30)
        ->and((float) $updated->tax_rate)->toBe(23.0);
});

// ---------------------------------------------------------------------------
// Legacy fallback
// ---------------------------------------------------------------------------

test('a line without a per-item rate falls back to the invoice-level rate', function () {
    $client = Client::factory()->create(['tax_exempt' => false, 'country' => 'US', 'state' => 'CA']);

    TaxRule::factory()->create(['country' => 'US', 'state' => 'CA', 'tax_rate' => 10.00000]);

    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0, 'tax_rate' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 50, 'qty' => 2, 'taxed' => true]);

    $updated = $service->recalculateTotals($invoice);

    expect((float) $updated->subtotal)->toBe(100.00)
        ->and((float) $updated->tax)->toBe(10.00)
        ->and((float) $updated->total)->toBe(110.00);
});

test('once one line carries a rate, a rate-less sibling is taxed at 0', function () {
    $client = Client::factory()->create(['tax_exempt' => false]);
    $service = app(InvoiceService::class);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0, 'tax_rate' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 100, 'qty' => 1, 'tax_rate' => 23]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 20, 'qty' => 1, 'taxed' => true]);

    $updated = $service->recalculateTotals($invoice);

    // The rate-less line is not silently taxed at the invoice rate once the
    // invoice has switched to per-item rates.
    expect((float) $updated->subtotal)->toBe(120.00)
        ->and((float) $updated->tax)->toBe(23.00)
        ->and((float) $updated->total)->toBe(143.00);
});

// ---------------------------------------------------------------------------
// addLineItem
// ---------------------------------------------------------------------------

test('addLineItem honours the provided quantity and VAT rate', function () {
    $client = Client::factory()->create(['tax_exempt' => false]);
    $service = app(InvoiceService::class);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0]);

    $service->addLineItem($invoice, [
        'description' => 'Hosting',
        'amount' => 100,
        'qty' => 3,
        'tax_rate' => 23,
    ]);

    $invoice->refresh();

    expect($invoice->items)->toHaveCount(1)
        ->and((int) $invoice->items->first()->qty)->toBe(3)
        ->and((float) $invoice->items->first()->tax_rate)->toBe(23.0)
        ->and((float) $invoice->subtotal)->toBe(300.00)
        ->and((float) $invoice->tax)->toBe(69.00)
        ->and((float) $invoice->total)->toBe(369.00);
});
