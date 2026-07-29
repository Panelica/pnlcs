<?php

use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * Commission on money that was handed back.
 *
 * Paying an invoice credits the referring affiliate. Refunding it took the
 * money off the customer's balance and out of the gateway, but left the
 * commission where it was: the business had paid a share of revenue it no
 * longer had, and nothing in the ledger said so.
 */
function referredInvoice(float $total, float $rate = 10): array
{
    $affiliate = Affiliate::create([
        'client_id' => Client::factory()->create()->id,
        'visitors' => 0,
        'pay_type' => 'percentage',
        'pay_amount' => $rate,
        'onetime' => false,
        'balance' => 0,
        'withdrawn' => 0,
    ]);

    $client = Client::factory()->create(['affiliate_id' => $affiliate->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => $total,
        'total' => $total,
        'payment_method' => 'banktransfer',
    ]);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-'.uniqid(), $total);

    return [$affiliate, $invoice];
}

test('refunding an invoice takes the commission back', function () {
    Mail::fake();

    [$affiliate, $invoice] = referredInvoice(100);

    expect($affiliate->fresh()->balance)->toEqual(10.0);

    $result = app(PaymentService::class)->refundInvoice($invoice->fresh(), null, ['gateway_refund' => false]);

    expect($result['success'])->toBeTrue()
        ->and($affiliate->fresh()->balance)->toEqual(0.0);
});

test('a part refund takes back the same part of the commission', function () {
    Mail::fake();

    [$affiliate, $invoice] = referredInvoice(200);

    expect($affiliate->fresh()->balance)->toEqual(20.0);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), 50, ['gateway_refund' => false]);

    expect($affiliate->fresh()->balance)->toEqual(15.0);
});

test('the reversal is written into the ledger', function () {
    Mail::fake();

    [$affiliate, $invoice] = referredInvoice(100);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), null, ['gateway_refund' => false]);

    $reversal = Transaction::where('client_id', $affiliate->client_id)
        ->where('invoice_id', $invoice->id)
        ->where('amount_out', '>', 0)
        ->first();

    expect($reversal)->not->toBeNull()
        ->and((float) $reversal->amount_out)->toEqual(10.0);
});

test('an affiliate who already withdrew is not pushed into the red', function () {
    Mail::fake();

    [$affiliate, $invoice] = referredInvoice(100);

    // Paid out before the refund came in.
    $affiliate->update(['balance' => 0, 'withdrawn' => 10]);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), null, ['gateway_refund' => false]);

    expect((float) $affiliate->fresh()->balance)->toEqual(0.0);
});

test('an unreferred invoice refunds as before', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => 40,
        'total' => 40,
        'payment_method' => 'banktransfer',
    ]);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-PLAIN', 40.0);

    expect(app(PaymentService::class)->refundInvoice($invoice->fresh(), null, ['gateway_refund' => false])['success'])
        ->toBeTrue();
});

/**
 * Commission is not a payment.
 *
 * It is written against the invoice it was earned on, but it belongs to the
 * affiliate, not to the customer. The paid-amount and balance sums counted
 * every row on the invoice, so a referred customer's settled invoice reported
 * more paid than the customer had ever handed over — and refunding "the lot"
 * handed back the difference in real money.
 */
test('a commission does not count as money the customer paid', function () {
    Mail::fake();

    [, $invoice] = referredInvoice(100);

    expect(app(PaymentService::class)->amountPaid($invoice->fresh()))->toEqual(100.0)
        ->and(app(PaymentService::class)->balance($invoice->fresh()))->toEqual(0.0);
});

test('refunding everything refunds what the customer paid', function () {
    Mail::fake();

    [, $invoice] = referredInvoice(100);

    $result = app(PaymentService::class)->refundInvoice($invoice->fresh(), null, ['gateway_refund' => false]);

    expect($result['amount'])->toEqual(100.0);
});

test('the refund is made against the gateway the customer paid with', function () {
    Mail::fake();

    [, $invoice] = referredInvoice(100);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), null, ['gateway_refund' => false]);

    $refund = Transaction::where('invoice_id', $invoice->id)
        ->where('amount_out', '>', 0)
        ->where('client_id', $invoice->client_id)
        ->first();

    expect($refund)->not->toBeNull()
        ->and($refund->gateway)->toBe('banktransfer');
});
