<?php

use App\Models\Client;
use App\Models\Product;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;

/*
 * Cancelling an order closes its invoice. It used to close it only when the
 * invoice was plain unpaid or overdue: one the customer had paid part of, or
 * had filed a transfer notification for, stayed open and kept being chased
 * for an order that no longer existed.
 */

function openOrder(): array
{
    $client = Client::factory()->create(['tax_exempt' => true, 'credit' => 0]);
    $product = Product::factory()->create(['tax' => false, 'auto_setup' => 'payment']);
    $order = app(OrderService::class)->processOrder($client, [
        ['type' => 'service', 'product_id' => $product->id, 'domain' => 'example.com', 'amount' => 100, 'billing_cycle' => 'Monthly'],
    ], 'banktransfer');

    return [$order, $client];
}

test('cancelling an order closes an invoice the customer had paid part of, and returns that part', function () {
    [$order, $client] = openOrder();
    app(PaymentService::class)->applyPayment($order->invoice, 'banktransfer', 'tx-part', 30.0);
    expect($order->invoice->fresh()->status)->toBe('partially_paid');

    app(OrderService::class)->cancelOrder($order->fresh());

    expect($order->invoice->fresh()->status)->toBe('cancelled')
        ->and((float) $client->fresh()->credit)->toBe(30.0);
});

test('cancelling an order closes an invoice awaiting transfer approval', function () {
    [$order] = openOrder();
    $order->invoice->update(['status' => 'payment_pending']);

    app(OrderService::class)->cancelOrder($order->fresh());

    expect($order->invoice->fresh()->status)->toBe('cancelled');
});

test('holding an order as fraud closes a partially paid invoice the same way', function () {
    [$order, $client] = openOrder();
    app(PaymentService::class)->applyPayment($order->invoice, 'banktransfer', 'tx-part2', 30.0);

    app(OrderService::class)->markFraud($order->fresh());

    expect($order->invoice->fresh()->status)->toBe('cancelled')
        ->and((float) $client->fresh()->credit)->toBe(30.0);
});

test('a paid invoice is never cancelled by an order cancellation', function () {
    [$order] = openOrder();
    app(InvoiceService::class)->markPaid($order->invoice, 'tx-full', 'banktransfer');

    app(OrderService::class)->cancelOrder($order->fresh());

    expect($order->invoice->fresh()->status)->toBe('paid');
});
