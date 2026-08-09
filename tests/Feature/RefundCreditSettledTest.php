<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\InvoiceService;
use App\Services\PaymentService;

/**
 * An invoice settled out of the customer's balance, which nothing could undo.
 *
 * Applying credit writes no transaction - no money moved, the balance simply
 * went down and the invoice's own credit column went up. The refund door counts
 * transactions, so for an invoice paid this way it answered "Nothing has been
 * paid on this invoice", and the cancel door refuses a paid invoice outright
 * with "a paid invoice is refunded, not cancelled". Each door pointed at the
 * other and the customer's money stayed where it was.
 *
 * Cancelling already knows credit is money: it hands the applied balance back,
 * restores the invoice total and writes the customer a credit entry. Refunding
 * now knows the same thing.
 */
function creditSettledInvoice(float $total = 100.0): Invoice
{
    $client = Client::factory()->create(['credit' => $total]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'A month of hosting', 'amount' => $total, 'qty' => 1],
    ]);

    app(InvoiceService::class)->applyCredit($invoice->fresh(), $total);

    return $invoice->fresh();
}

it('refunds an invoice the customer paid out of their balance', function () {
    $invoice = creditSettledInvoice();

    expect($invoice->status)->toBe(InvoiceStatus::Paid->value);

    $result = app(PaymentService::class)->refundInvoice($invoice, null, ['gateway_refund' => false]);

    expect($result['success'])->toBeTrue();
});

it('puts the balance back where it came from', function () {
    $invoice = creditSettledInvoice();
    $client = $invoice->client;

    expect((float) $client->fresh()->credit)->toBe(0.0);

    app(PaymentService::class)->refundInvoice($invoice, null, ['gateway_refund' => false]);

    expect((float) $client->fresh()->credit)->toBe(100.0);
});

it('marks the invoice refunded', function () {
    $invoice = creditSettledInvoice();

    app(PaymentService::class)->refundInvoice($invoice, null, ['gateway_refund' => false]);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Refunded->value);
});

it('gives back only what was asked for', function () {
    $invoice = creditSettledInvoice();
    $client = $invoice->client;

    app(PaymentService::class)->refundInvoice($invoice, 40.0, ['gateway_refund' => false]);

    expect((float) $client->fresh()->credit)->toBe(40.0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid->value);
});

it('still refuses more than was ever paid', function () {
    $invoice = creditSettledInvoice();

    $result = app(PaymentService::class)->refundInvoice($invoice, 150.0, ['gateway_refund' => false]);

    expect($result['success'])->toBeFalse();
});

it('still says nothing was paid when nothing was', function () {
    $client = Client::factory()->create(['credit' => 0]);
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Unpaid', 'amount' => 50, 'qty' => 1],
    ]);

    $result = app(PaymentService::class)->refundInvoice($invoice->fresh(), null, ['gateway_refund' => false]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Nothing has been paid');
});

it('still refunds money that really moved', function () {
    $client = Client::factory()->create(['credit' => 0]);
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Paid by transfer', 'amount' => 60, 'qty' => 1],
    ]);
    app(PaymentService::class)->applyPayment($invoice->fresh(), 'banktransfer', 'TXN-REAL-1', 60.0);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), null, ['gateway_refund' => false]);

    expect((float) Transaction::where('invoice_id', $invoice->id)->sum('amount_out'))->toBe(60.0)
        ->and((float) $client->fresh()->credit)->toBe(0.0);
});
