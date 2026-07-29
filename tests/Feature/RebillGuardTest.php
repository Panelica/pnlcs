<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\InvoiceGenerationService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * Billing the same renewal twice.
 *
 * A service is left alone once it has been invoiced, but only while that
 * invoice is unpaid or overdue. A customer who pays part of it, or whose bank
 * transfer is waiting to be confirmed, moves the invoice to a status the guard
 * does not recognise — and the nightly run bills the same renewal again, every
 * night, until they settle it.
 */
function dueService(): Service
{
    $client = Client::factory()->create(['tax_exempt' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
    ]);

    return Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'auto_renew' => true,
        'billing_cycle' => 'Monthly',
        'amount' => 20,
        'next_due_date' => now()->addDay()->toDateString(),
    ]);
}

test('a renewal already invoiced is not invoiced again', function () {
    Mail::fake();
    dueService();

    app(InvoiceGenerationService::class)->generateDueInvoices();
    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(Invoice::count())->toBe(1);
});

test('a part paid renewal is not invoiced again', function () {
    Mail::fake();
    dueService();

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $invoice = Invoice::firstOrFail();
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-PART', 5.0);

    expect($invoice->fresh()->status)->toBe('partially_paid');

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(Invoice::count())->toBe(1);
});

test('a renewal waiting for payment confirmation is not invoiced again', function () {
    Mail::fake();
    dueService();

    app(InvoiceGenerationService::class)->generateDueInvoices();

    // What the bank transfer flow does while an operator checks the receipt.
    Invoice::query()->update(['status' => 'payment_pending']);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(Invoice::count())->toBe(1);
});

test('a renewal whose invoice was cancelled is invoiced again', function () {
    Mail::fake();
    dueService();

    app(InvoiceGenerationService::class)->generateDueInvoices();

    Invoice::query()->update(['status' => 'cancelled']);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(Invoice::count())->toBe(2);
});
