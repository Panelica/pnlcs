<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Service;
use App\Services\InvoiceService;
use App\Services\PaymentService;

/**
 * Domain renewal invoicing + renewal date advancement on payment.
 *
 * Before this work the generator only scanned services, so registered domains
 * never got a renewal invoice, and no path advanced next_due_date on payment —
 * meaning a paid service renewal would be re-invoiced the next day.
 */

function dueDomain(array $o = []): Domain
{
    $client = Client::factory()->create();
    return Domain::factory()->create(array_merge([
        'client_id'           => $client->id,
        'status'              => 'active',
        'registration_period' => 1,
        'next_due_date'       => now()->addDays(5),
        'expiry_date'         => now()->addDays(5),
        'recurring_amount'    => 20.00,
    ], $o));
}

it('generates a renewal invoice for a domain due within the window', function () {
    $domain = dueDomain();

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $item = InvoiceItem::where('type', 'Domain')->where('rel_id', $domain->id)->first();
    expect($item)->not->toBeNull()
        ->and((float) $item->amount)->toBe(20.00);
});

it('does not double-invoice a domain that already has an open renewal invoice', function () {
    $domain = dueDomain();

    $this->artisan('pnlcs:generate-invoices');
    $this->artisan('pnlcs:generate-invoices');

    expect(InvoiceItem::where('type', 'Domain')->where('rel_id', $domain->id)->count())->toBe(1);
});

it('does not invoice a domain that is not yet due', function () {
    $domain = dueDomain(['next_due_date' => now()->addDays(60), 'expiry_date' => now()->addDays(60)]);

    $this->artisan('pnlcs:generate-invoices');

    expect(InvoiceItem::where('type', 'Domain')->where('rel_id', $domain->id)->count())->toBe(0);
});

it('advances domain dates on renewal payment and does not re-invoice afterwards', function () {
    $domain    = dueDomain();
    $oldExpiry = $domain->expiry_date->copy();

    $this->artisan('pnlcs:generate-invoices');
    $invoice = Invoice::whereHas('items', fn ($q) => $q->where('type', 'Domain')->where('rel_id', $domain->id))->first();
    expect($invoice)->not->toBeNull();

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-D1', (float) $invoice->total);

    $domain->refresh();
    expect($domain->expiry_date->toDateString())->toBe($oldExpiry->copy()->addYear()->toDateString())
        ->and($domain->next_due_date->toDateString())->toBe($oldExpiry->copy()->addYear()->toDateString());

    // out of the window now → no second invoice
    $this->artisan('pnlcs:generate-invoices');
    expect(InvoiceItem::where('type', 'Domain')->where('rel_id', $domain->id)->count())->toBe(1);
});

it('advances a service next_due_date when its renewal invoice is paid', function () {
    $client  = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id, 'status' => 'active',
        'billing_cycle' => 'Monthly', 'amount' => 10.00,
        'next_due_date' => now()->addDays(5),
    ]);
    $due = $service->next_due_date->copy();

    // Renewal invoice = Hosting item with NO order referencing the invoice.
    $invoice = app(InvoiceService::class)->createInvoice($client, [[
        'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'renew', 'amount' => 10.00, 'taxed' => false,
    ]]);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-S1', (float) $invoice->total);

    $service->refresh();
    expect($service->next_due_date->toDateString())->toBe($due->copy()->addMonth()->toDateString());
});

it('does not advance a service for a new-order invoice (order references the invoice)', function () {
    $client  = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id, 'status' => 'active',
        'billing_cycle' => 'Monthly', 'amount' => 10.00,
        'next_due_date' => now()->addDays(5),
    ]);
    $due = $service->next_due_date->copy();

    $invoice = app(InvoiceService::class)->createInvoice($client, [[
        'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'new order', 'amount' => 10.00, 'taxed' => false,
    ]]);
    // Non-pending order so AutoAcceptOrderListener ignores it, isolating the
    // renewal discriminator: an order exists for this invoice → not a renewal.
    Order::factory()->create(['client_id' => $client->id, 'invoice_id' => $invoice->id, 'status' => 'active']);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-S2', (float) $invoice->total);

    $service->refresh();
    expect($service->next_due_date->toDateString())->toBe($due->toDateString());
});
