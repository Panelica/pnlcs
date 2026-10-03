<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Services\PaymentService;

/*
 * An invoice is created with the method chosen at checkout. A customer who
 * picked bank transfer and then paid by card read "paid via bank transfer"
 * on the paid invoice, because paying never changed the method.
 */

function pimInvoice(): Invoice
{
    return Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 50, 'payment_method' => 'banktransfer']);
}

test('the invoice names the gateway that settled it', function () {
    $invoice = pimInvoice();

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'pi_1');

    expect($invoice->fresh())->status->toBe('paid')->payment_method->toBe('stripe');
});

test('a part payment leaves the method until the invoice is settled', function () {
    $invoice = pimInvoice();

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'pi_2', 20.0);
    expect($invoice->fresh()->payment_method)->toBe('banktransfer');

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'pi_3', 30.0);
    expect($invoice->fresh())->status->toBe('paid')->payment_method->toBe('stripe');
});

test('a payment marked by hand keeps the method the invoice has', function () {
    $invoice = pimInvoice();

    app(PaymentService::class)->applyPayment($invoice, 'manual', null);

    expect($invoice->fresh())->status->toBe('paid')->payment_method->toBe('banktransfer');
});
