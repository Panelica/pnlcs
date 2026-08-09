<?php

use App\Models\CancellationRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\InvoiceGenerationService;

/**
 * Invoicing a customer for a period they have already asked to end.
 *
 * A cancellation asked for the end of the billing period leaves the service
 * running until its due date - and the generator issues renewal invoices a
 * fortnight before that date, so the invoice for the period they cancelled
 * arrived anyway. Nothing they could do with it: paying is buying back what
 * they asked to stop, and not paying puts the account through overdue marking,
 * late fees, reminders and the suspension chain before the cancellation job
 * ends the service anyway.
 *
 * The same query already honours the quieter version of this statement:
 * auto_renew is checked, with a note saying that billing somebody who turned
 * renewal off is what got an account suspended for an invoice they never
 * wanted. A cancellation request says it louder.
 */
function serviceAskedToCancel(?string $type, bool $processed = false): Service
{
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => 100.0,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3)->toDateString(),
        'auto_renew' => true,
        'domain' => 'cancelling-'.uniqid().'.test',
    ]);

    if ($type !== null) {
        CancellationRequest::create([
            'service_id' => $service->id,
            'type' => $type,
            'reason' => 'Moving elsewhere',
            'processed_at' => $processed ? now() : null,
        ]);
    }

    return $service;
}

function addonOnCancellingService(Service $service): ServiceAddon
{
    $addon = ProductAddon::create([
        'name' => 'Dedicated IP',
        'packages' => (string) $service->product_id,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 1,
        'tax' => false,
    ]);

    return ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $addon->id,
        'client_id' => $service->client_id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'status' => 'active',
    ]);
}

function billedItemsFor(Service $service): int
{
    app(InvoiceGenerationService::class)->generateDueInvoices();

    return InvoiceItem::whereIn('invoice_id', Invoice::where('client_id', $service->client_id)->pluck('id'))->count();
}

it('does not invoice a service the customer asked to end', function () {
    $service = serviceAskedToCancel('end_of_billing');

    expect(billedItemsFor($service))->toBe(0);
});

it('does not invoice an extra on a service the customer asked to end', function () {
    $service = serviceAskedToCancel('end_of_billing');
    addonOnCancellingService($service);

    expect(billedItemsFor($service))->toBe(0);
});

it('does not invoice a service asked to end at once either', function () {
    $service = serviceAskedToCancel('immediate');

    expect(billedItemsFor($service))->toBe(0);
});

it('bills again once the request has been dealt with', function () {
    $service = serviceAskedToCancel('end_of_billing', processed: true);

    expect(billedItemsFor($service))->toBeGreaterThan(0);
});

it('still bills a service nobody asked to end', function () {
    $service = serviceAskedToCancel(null);

    expect(billedItemsFor($service))->toBeGreaterThan(0);
});

it('still leaves out a service whose renewal the customer switched off', function () {
    $service = serviceAskedToCancel(null);
    $service->update(['auto_renew' => false]);

    expect(billedItemsFor($service))->toBe(0);
});
