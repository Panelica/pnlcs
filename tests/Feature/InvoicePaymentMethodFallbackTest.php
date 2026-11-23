<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Services\InvoiceGenerationService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Mail;

/*
 * A generated invoice used to carry no payment method at all. The built-in
 * PDF hid the row, but the uploaded language templates print
 * "Payment method: {payment_method}" unconditionally, so every renewal and
 * add-on proforma showed an empty line.
 */

it('stores the client payment method on a generated invoice', function () {
    Mail::fake();
    Setting::set('ProformaEnabled', '1');

    $client = Client::factory()->create([
        'default_payment_method' => 'banktransfer',
        'tax_exempt' => true,
    ]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
    ]);
    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'auto_renew' => true,
        'billing_cycle' => 'Monthly',
        'amount' => 10,
        'next_due_date' => now()->addDays(3),
    ]);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect($invoice->isProforma())->toBeTrue()
        ->and($invoice->payment_method)->toBe('banktransfer');
});

it('keeps an explicitly chosen payment method', function () {
    $client = Client::factory()->create(['default_payment_method' => 'banktransfer']);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 10, 'taxed' => false],
    ], ['payment_method' => 'stripe']);

    expect($invoice->payment_method)->toBe('stripe');
});

it('falls back to bank transfer when the client has no usable method', function () {
    foreach ([null, '', 'none'] as $default) {
        $client = Client::factory()->create(['default_payment_method' => $default]);

        $invoice = app(InvoiceService::class)->createInvoice($client, [
            ['description' => 'Hosting', 'amount' => 10, 'taxed' => false],
        ]);

        expect($invoice->payment_method)->toBe('banktransfer');
    }
});

it('shows a fallback method on an already issued proforma without rewriting it', function () {
    $client = Client::factory()->create(['default_payment_method' => 'banktransfer']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'type' => 'proforma',
        'language' => 'pl',
        'payment_method' => null,
        'subtotal' => 10,
        'total' => 10,
    ]);

    $vars = app(InvoicePdfService::class)->placeholderVars($invoice);

    expect($invoice->fresh()->payment_method)->toBeNull()
        ->and($invoice->displayPaymentMethod())->toBe('banktransfer')
        ->and($vars['payment_method'])->toBe(__('messages.payment_method.banktransfer', [], 'pl'));
});

it('prefers the method stored on the document over the fallback', function () {
    $client = Client::factory()->create(['default_payment_method' => 'banktransfer']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'payment_method' => 'manual',
    ]);

    expect($invoice->displayPaymentMethod())->toBe('manual');
});
