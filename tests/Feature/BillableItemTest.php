<?php

use App\Models\BillableItem;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\InvoiceGenerationService;
use Illuminate\Support\Facades\Mail;

/**
 * One-off charges the operator adds to an account.
 *
 * There is a screen for them and an API endpoint, and the table carries an
 * invoice_id column for the invoice they end up on. Nothing ever put them on
 * one: the invoice generator collects services, addons and domains and has
 * never looked at this table. Thirty-two charges are recorded here, none
 * invoiced, 939.69 in the shop currency that was entered and forgotten.
 */
function billableClient(): Client
{
    return Client::factory()->create(['country' => 'TR', 'tax_exempt' => true]);
}

function billableDueService(Client $client): Service
{
    return Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => Product::factory()->create([
            'group_id' => ProductGroup::factory()->create()->id,
            'tax' => false,
        ])->id,
        'server_id' => null,
        'status' => 'active',
        'auto_renew' => true,
        'domain' => 'billable-example.com',
        'amount' => 100,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
    ]);
}

test('a one-off charge reaches an invoice', function () {
    Mail::fake();
    $client = billableClient();
    billableDueService($client);

    $charge = BillableItem::create([
        'client_id' => $client->id,
        'description' => 'Migration assistance',
        'amount' => 45,
        'due_date' => now()->addDay(),
    ]);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect($invoice->items->pluck('description'))->toContain('Migration assistance')
        ->and((float) $invoice->total)->toBe(145.0)
        ->and($charge->fresh()->invoice_id)->toBe($invoice->id);
});

test('a charge already billed is not billed again', function () {
    Mail::fake();
    $client = billableClient();
    billableDueService($client);

    BillableItem::create([
        'client_id' => $client->id,
        'description' => 'Migration assistance',
        'amount' => 45,
        'due_date' => now()->addDay(),
    ]);

    app(InvoiceGenerationService::class)->generateDueInvoices();
    $first = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    // A second run, with the service due again.
    Service::where('client_id', $client->id)->update(['next_due_date' => now()->addDays(3)]);
    app(InvoiceGenerationService::class)->generateDueInvoices();

    $charges = Invoice::where('client_id', $client->id)
        ->with('items')
        ->get()
        ->flatMap->items
        ->where('description', 'Migration assistance');

    expect($charges)->toHaveCount(1)
        ->and($first->fresh()->items->count())->toBeGreaterThan(1);
});

test('a charge that is not due yet waits', function () {
    Mail::fake();
    $client = billableClient();
    billableDueService($client);

    $later = BillableItem::create([
        'client_id' => $client->id,
        'description' => 'Next year work',
        'amount' => 60,
        'due_date' => now()->addMonths(6),
    ]);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect($later->fresh()->invoice_id)->toBeNull();
});

test('a charge on a client with nothing else due is still billed', function () {
    Mail::fake();
    $client = billableClient();

    $charge = BillableItem::create([
        'client_id' => $client->id,
        'description' => 'Consultancy',
        'amount' => 200,
        'due_date' => now(),
    ]);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect($charge->fresh()->invoice_id)->not->toBeNull();
});
