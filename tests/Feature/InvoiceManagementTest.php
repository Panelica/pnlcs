<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRule;
use App\Models\Transaction;
use App\Services\InvoiceService;


// ---------------------------------------------------------------------------
// Create invoice with items
// ---------------------------------------------------------------------------

test('create invoice generates invoice with correct number of items', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $service = app(InvoiceService::class);

    $invoice = $service->createInvoice($client, [
        ['description' => 'Web Hosting Monthly', 'amount' => 9.99, 'taxed' => false],
        ['description' => 'SSL Certificate',     'amount' => 4.99, 'taxed' => false],
    ]);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->items->count())->toBe(2)
        ->and($invoice->client_id)->toBe($client->id);
});

test('create invoice calculates subtotal correctly', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $service = app(InvoiceService::class);

    $invoice = $service->createInvoice($client, [
        ['description' => 'Item A', 'amount' => 10.00, 'taxed' => false],
        ['description' => 'Item B', 'amount' => 20.50, 'taxed' => false],
    ]);

    expect((float) $invoice->subtotal)->toBe(30.50)
        ->and((float) $invoice->total)->toBe(30.50);
});

test('create invoice auto-generates invoice number with prefix', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $service = app(InvoiceService::class);

    $invoice = $service->createInvoice($client, [
        ['description' => 'Test item', 'amount' => 5.00, 'taxed' => false],
    ]);

    expect($invoice->invoice_num)->toStartWith('INV-')
        ->and(strlen($invoice->invoice_num))->toBeGreaterThan(4);
});

test('create invoice sets status to Unpaid by default', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $service = app(InvoiceService::class);

    $invoice = $service->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 9.99, 'taxed' => false],
    ]);

    expect($invoice->status)->toBe('unpaid');
});

test('create invoice uses provided date and due_date', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $service = app(InvoiceService::class);

    $invoice = $service->createInvoice($client, [
        ['description' => 'Item', 'amount' => 9.99, 'taxed' => false],
    ], [
        'date'     => '2025-01-01',
        'due_date' => '2025-01-15',
    ]);

    expect($invoice->date->format('Y-m-d'))->toBe('2025-01-01')
        ->and($invoice->due_date->format('Y-m-d'))->toBe('2025-01-15');
});

// ---------------------------------------------------------------------------
// Recalculate totals
// ---------------------------------------------------------------------------

test('recalculate totals sums all items correctly', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $service = app(InvoiceService::class);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 15.00, 'taxed' => false]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 5.00,  'taxed' => false]);

    $updated = $service->recalculateTotals($invoice);

    expect((float) $updated->subtotal)->toBe(20.00)
        ->and((float) $updated->total)->toBe(20.00);
});

test('recalculate totals applies tax for taxable items', function () {
    $client = Client::factory()->create([
        'tax_exempt' => false,
        'country'    => 'US',
        'state'      => 'CA',
    ]);

    TaxRule::factory()->create([
        'country'  => 'US',
        'state'    => 'CA',
        'tax_rate' => 10.00000,
    ]);

    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0, 'tax_rate' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 100.00, 'taxed' => true]);

    $updated = $service->recalculateTotals($invoice);

    expect((float) $updated->subtotal)->toBe(100.00)
        ->and((float) $updated->tax)->toBe(10.00)
        ->and((float) $updated->total)->toBe(110.00);
});

test('recalculate totals skips tax for non-taxed items', function () {
    $client = Client::factory()->create([
        'tax_exempt' => false,
        'country'    => 'US',
        'state'      => 'TX',
    ]);

    TaxRule::factory()->create(['country' => 'US', 'state' => 'TX', 'tax_rate' => 8.00000]);

    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'subtotal' => 0, 'total' => 0, 'tax_rate' => 0]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'amount' => 50.00, 'taxed' => false]);

    $updated = $service->recalculateTotals($invoice);

    expect((float) $updated->tax)->toBe(0.0)
        ->and((float) $updated->total)->toBe(50.00);
});

// ---------------------------------------------------------------------------
// Mark paid creates transaction
// ---------------------------------------------------------------------------

test('mark paid changes invoice status to Paid', function () {
    $client  = Client::factory()->create();
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 29.99]);

    $updated = $service->markPaid($invoice);

    expect($updated->status)->toBe('paid')
        ->and($updated->date_paid)->not->toBeNull();
});

test('mark paid creates a transaction record', function () {
    $client  = Client::factory()->create();
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 49.00]);

    $service->markPaid($invoice, 'TXN-12345', 'stripe');

    $tx = Transaction::where('invoice_id', $invoice->id)->first();

    expect($tx)->not->toBeNull()
        ->and($tx->transaction_id)->toBe('TXN-12345')
        ->and($tx->gateway)->toBe('stripe')
        ->and((float) $tx->amount_in)->toBe(49.00);
});

test('mark paid is idempotent for already-paid invoices', function () {
    $client  = Client::factory()->create();
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->paid()->create(['client_id' => $client->id, 'total' => 10.00]);

    $service->markPaid($invoice);
    $service->markPaid($invoice);

    expect(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Apply credit
// ---------------------------------------------------------------------------

test('apply credit reduces invoice total', function () {
    $client = Client::factory()->create(['credit' => 50.00]);
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status'    => 'unpaid',
        'subtotal'  => 30.00,
        'total'     => 30.00,
        'credit'    => 0,
    ]);

    $updated = $service->applyCredit($invoice, 10.00);

    expect((float) $updated->credit)->toBe(10.00)
        ->and((float) $updated->total)->toBe(20.00);
});

test('apply credit deducts from client credit balance', function () {
    $client = Client::factory()->create(['credit' => 100.00]);
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status'    => 'unpaid',
        'subtotal'  => 50.00,
        'total'     => 50.00,
        'credit'    => 0,
    ]);

    $service->applyCredit($invoice, 20.00);

    expect((float) $client->fresh()->credit)->toBe(80.00);
});

test('apply credit cannot exceed available client credit', function () {
    $client = Client::factory()->create(['credit' => 5.00]);
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status'    => 'unpaid',
        'subtotal'  => 100.00,
        'total'     => 100.00,
        'credit'    => 0,
    ]);

    $updated = $service->applyCredit($invoice, 50.00);

    // Capped at client's 5.00 credit
    expect((float) $updated->credit)->toBe(5.00)
        ->and((float) $client->fresh()->credit)->toBe(0.00);
});

test('apply credit auto-marks invoice paid when fully covered', function () {
    $client = Client::factory()->create(['credit' => 100.00]);
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status'    => 'unpaid',
        'subtotal'  => 20.00,
        'total'     => 20.00,
        'credit'    => 0,
    ]);

    $service->applyCredit($invoice, 20.00);

    expect($invoice->fresh()->status)->toBe('paid');
});

test('apply credit does not affect paid invoices', function () {
    $client = Client::factory()->create(['credit' => 50.00]);
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->paid()->create([
        'client_id' => $client->id,
        'total'     => 20.00,
        'credit'    => 0,
    ]);

    $service->applyCredit($invoice, 10.00);

    expect((float) $client->fresh()->credit)->toBe(50.00);
});

// ---------------------------------------------------------------------------
// Cancel invoice
// ---------------------------------------------------------------------------

test('cancel invoice sets status to Cancelled', function () {
    $client  = Client::factory()->create();
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid']);

    $updated = $service->cancelInvoice($invoice);

    expect($updated->status)->toBe('cancelled');
});

test('cancel invoice does not cancel paid invoices', function () {
    $client  = Client::factory()->create();
    $service = app(InvoiceService::class);
    $invoice = Invoice::factory()->paid()->create(['client_id' => $client->id]);

    $updated = $service->cancelInvoice($invoice);

    expect($updated->status)->toBe('paid');
});

// ---------------------------------------------------------------------------
// Generate invoice number
// ---------------------------------------------------------------------------

test('generate invoice number increments from last invoice', function () {
    $client = Client::factory()->create();
    Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-000005']);

    $service = app(InvoiceService::class);
    $num = $service->generateInvoiceNumber();

    expect($num)->toBe('INV-000006');
});

test('generate invoice number starts at 000001 when no invoices exist', function () {
    $service = app(InvoiceService::class);
    $num = $service->generateInvoiceNumber();

    expect($num)->toBe('INV-000001');
});
