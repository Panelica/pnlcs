<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

// Account credit spent on an invoice is a payment, not a discount: the total
// stays the invoice's gross value and credit is taken off in amountDue().
// These tests pin that invariant down on its own.

function creditInvoice(Client $client, float $amount): Invoice
{
    return app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => $amount, 'taxed' => false],
    ]);
}

test('spending credit keeps the invoice total at its gross value', function () {
    Mail::fake();
    $client = Client::factory()->create(['credit' => 30, 'tax_exempt' => true]);

    $invoice = creditInvoice($client, 100.0);

    expect((float) $invoice->total)->toBe(100.0)
        ->and((float) $invoice->credit)->toBe(30.0)
        ->and($invoice->amountDue())->toBe(70.0)
        ->and((float) $client->fresh()->credit)->toBe(0.0);
});

test('cancelling an invoice returns the credit and keeps the total gross', function () {
    Mail::fake();
    $client = Client::factory()->create(['credit' => 30, 'tax_exempt' => true]);
    $service = app(InvoiceService::class);

    $invoice = creditInvoice($client, 100.0);
    $service->cancelInvoice($invoice);

    expect((float) $invoice->fresh()->total)->toBe(100.0)
        ->and((float) $invoice->fresh()->credit)->toBe(0.0)
        ->and((float) $client->fresh()->credit)->toBe(30.0);
});

test('the balance left after credit settles the invoice', function () {
    Mail::fake();
    $client = Client::factory()->create(['credit' => 30, 'tax_exempt' => true]);
    $invoice = creditInvoice($client, 100.0);

    app(PaymentService::class)->applyPayment(
        $invoice->fresh(),
        'banktransfer',
        'TXN-CREDIT-1',
        $invoice->fresh()->amountDue(),
    );

    $settled = $invoice->fresh();
    expect($settled->status)->toBe('paid')
        ->and($settled->amountDue())->toBe(0.0)
        ->and((float) $settled->total)->toBe(100.0);
});
