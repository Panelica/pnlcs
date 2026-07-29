<?php

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * Cancelling an invoice somebody has already paid part of.
 *
 * Cancelling only flipped the status. A customer who had paid 40 of a 100
 * invoice lost the 40: it stayed recorded against a cancelled invoice, was
 * never refunded and never reached their balance, so there was nothing left
 * for them to spend and nothing on the invoice to explain where it went.
 *
 * The same money arriving on an already-settled invoice has always become
 * credit; this is the same rule applied at the other end.
 */
function partPaidInvoice(float $total, float $paid): array
{
    $client = Client::factory()->create(['credit' => 0]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => $total,
        'total' => $total,
    ]);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-'.uniqid(), $paid);

    return [$client, $invoice->fresh()];
}

test('what was paid becomes credit the customer can still spend', function () {
    Mail::fake();

    [$client, $invoice] = partPaidInvoice(100, 40);

    expect($invoice->status)->toBe('partially_paid');

    app(InvoiceService::class)->cancelInvoice($invoice);

    expect($invoice->fresh()->status)->toBe('cancelled')
        ->and((float) $client->fresh()->credit)->toEqual(40.0);
});

test('the ledger says where it went', function () {
    Mail::fake();

    [$client, $invoice] = partPaidInvoice(100, 40);

    app(InvoiceService::class)->cancelInvoice($invoice);

    $entry = Credit::where('client_id', $client->id)->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and((float) $entry->amount)->toEqual(40.0)
        ->and($entry->description)->toContain((string) $invoice->invoice_num);
});

test('cancelling an untouched invoice credits nothing', function () {
    Mail::fake();

    $client = Client::factory()->create(['credit' => 0]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 60]);

    app(InvoiceService::class)->cancelInvoice($invoice);

    expect($invoice->fresh()->status)->toBe('cancelled')
        ->and((float) $client->fresh()->credit)->toEqual(0.0);
});

test('cancelling twice does not credit twice', function () {
    Mail::fake();

    [$client, $invoice] = partPaidInvoice(100, 40);

    app(InvoiceService::class)->cancelInvoice($invoice);
    app(InvoiceService::class)->cancelInvoice($invoice->fresh());

    expect((float) $client->fresh()->credit)->toEqual(40.0);
});

test('a paid invoice is still refused', function () {
    Mail::fake();

    [$client, $invoice] = partPaidInvoice(100, 100);

    expect($invoice->status)->toBe('paid');

    app(InvoiceService::class)->cancelInvoice($invoice);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) $client->fresh()->credit)->toEqual(0.0);
});
