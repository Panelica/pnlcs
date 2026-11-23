<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;

/*
 * An invoice is the document, not a view over the account. Changing a price,
 * deleting the service or the addon, or removing the customer must not alter
 * what was issued. The buyer and seller are snapshotted at issue time; the
 * lines store their own description and amount; the client foreign keys no
 * longer cascade the document away.
 */

it('keeps an issued invoice when its service is deleted', function () {
    $client = Client::factory()->create();
    $service = Service::factory()->create(['client_id' => $client->id]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [[
        'type' => 'Hosting',
        'rel_id' => $service->id,
        'description' => 'Hosting '.$service->domain,
        'amount' => 100,
    ]]);

    $item = $invoice->items()->first();

    // The service changes and then goes away; the document does not.
    $service->update(['amount' => 9999]);
    $service->delete();

    $invoice->refresh();
    $kept = $invoice->items()->first();

    expect($invoice->items()->count())->toBe(1)
        ->and($kept->description)->toBe($item->description)
        ->and((float) $kept->amount)->toBe(100.0);

    $vars = app(InvoicePdfService::class)->placeholderVars($invoice);
    expect($vars['items'])->toContain($item->description);
});

it('keeps an issued invoice when its addon is deleted', function () {
    $client = Client::factory()->create();
    $service = Service::factory()->create(['client_id' => $client->id]);

    $addon = ServiceAddon::create([
        'service_id' => $service->id,
        'client_id' => $client->id,
        'addon_id' => null,
        'qty' => 1,
        'amount' => 50,
        'billing_cycle' => 'Annually',
        'next_due_date' => now()->addYear(),
        'status' => 'active',
    ]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [[
        'type' => 'Addon',
        'rel_id' => $addon->id,
        'description' => 'Extra backup',
        'amount' => 50,
    ]]);

    $addon->delete();

    $invoice->refresh();
    $kept = $invoice->items()->first();

    expect($invoice->items()->count())->toBe(1)
        ->and($kept->description)->toBe('Extra backup')
        ->and((float) $kept->amount)->toBe(50.0);
});

it('keeps the buyer on an issued invoice after the client is soft-deleted', function () {
    $client = Client::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'address1' => '12 Dorset Street',
    ]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ]);

    $client->delete();
    $invoice->refresh();

    expect($invoice)->not->toBeNull()
        ->and($invoice->client)->toBeNull()
        ->and($invoice->buyer('first_name'))->toBe('Ada')
        ->and($invoice->buyer('address1'))->toBe('12 Dorset Street');
});

it('keeps the document when the client is hard-deleted', function () {
    $client = Client::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ]);

    $itemId = $invoice->items()->first()->id;

    $client->forceDelete();
    $invoice->refresh();

    expect($invoice)->not->toBeNull()
        ->and($invoice->client_id)->toBeNull()
        ->and($invoice->buyer('first_name'))->toBe('Grace')
        // The line keeps its own money; only the dangling owner id is nulled.
        ->and(InvoiceItem::whereKey($itemId)->value('client_id'))->toBeNull()
        ->and((float) InvoiceItem::whereKey($itemId)->value('amount'))->toBe(100.0);
});

/*
 * A document whose customer is gone still has to be workable: the totals are
 * recalculated whenever a line is touched (an addon removed, a line edited),
 * and that read the customer's tax exemption through a plain ->client->, which
 * is fatal once the row can be NULL.
 */
it('recalculates an invoice whose client was hard-deleted', function () {
    $client = Client::factory()->create();

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100, 'taxed' => true],
    ]);

    // A stored rate of zero is what sends recalculateTotals to the customer.
    $invoice->update(['tax_rate' => 0]);

    $client->forceDelete();
    $invoice->refresh();

    $recalculated = app(InvoiceService::class)->recalculateTotals($invoice);

    expect($recalculated->client_id)->toBeNull()
        ->and((float) $recalculated->subtotal)->toBe(100.0)
        ->and((float) $recalculated->total)->toBe(100.0);
});
