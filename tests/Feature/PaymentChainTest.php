<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\OrderService;
use App\Services\PaymentService;

// ---------------------------------------------------------------------------
// PaymentService — single payment entry point
// ---------------------------------------------------------------------------

test('full payment marks invoice paid and records transaction', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50.00]);

    $result = app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-FULL-1', 50.00);

    expect($result['status'])->toBe('paid')
        ->and($result['balance'])->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('partial payment sets partially_paid and keeps balance open', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.00]);

    $result = app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-PART-1', 40.00);

    expect($result['status'])->toBe('partially_paid')
        ->and($result['balance'])->toBe(60.0)
        ->and($invoice->fresh()->status)->toBe('partially_paid')
        ->and($invoice->fresh()->date_paid)->toBeNull();
});

test('second partial payment completes the invoice', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.00]);
    $payments = app(PaymentService::class);

    $payments->applyPayment($invoice, 'banktransfer', 'TXN-P1', 40.00);
    $result = $payments->applyPayment($invoice->fresh(), 'banktransfer', 'TXN-P2', 60.00);

    expect($result['status'])->toBe('paid')
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(2);
});

test('overpayment credits the client balance', function () {
    $client  = Client::factory()->create(['credit' => 0]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 30.00]);

    app(PaymentService::class)->applyPayment($invoice, 'paypal', 'TXN-OVER-1', 50.00);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) $client->fresh()->credit)->toBe(20.0);
});

test('duplicate gateway transaction is ignored', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 25.00]);
    $payments = app(PaymentService::class);

    $payments->applyPayment($invoice, 'stripe', 'TXN-DUP', 25.00);
    $result = $payments->applyPayment($invoice->fresh(), 'stripe', 'TXN-DUP', 25.00);

    expect($result['duplicate'] ?? false)->toBeTrue()
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('payment on a paid invoice becomes client credit', function () {
    $client  = Client::factory()->create(['credit' => 0]);
    $invoice = Invoice::factory()->paid()->create(['client_id' => $client->id, 'total' => 10.00]);

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-LATE-1', 10.00);

    expect((float) $client->fresh()->credit)->toBe(10.0);
});

// ---------------------------------------------------------------------------
// Payment → auto-accept → provisioning chain
// ---------------------------------------------------------------------------

test('paying the order invoice provisions the service end to end', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false, 'server_type' => 'custom', 'auto_setup' => 'payment']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id,
        'domain' => 'chain-test.com', 'amount' => 9.99, 'billing_cycle' => 'Monthly',
    ]], 'stripe');

    $invoice = Invoice::find($order->invoice_id);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-CHAIN-1', (float) $invoice->total);

    $service = Service::where('order_id', $order->id)->first();

    expect($order->fresh()->status)->toBe('active')
        ->and($service->status)->toBe('active');
});

test('manual auto_setup keeps order pending after payment', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false, 'server_type' => 'custom', 'auto_setup' => 'manual']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id,
        'domain' => 'manual-test.com', 'amount' => 9.99, 'billing_cycle' => 'Monthly',
    ]], 'stripe');

    $invoice = Invoice::find($order->invoice_id);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-MAN-1', (float) $invoice->total);

    $service = Service::where('order_id', $order->id)->first();

    expect($order->fresh()->status)->toBe('pending')
        ->and($service->status)->toBe('pending');
});

test('admin manual accept provisions manual auto_setup services', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false, 'server_type' => 'custom', 'auto_setup' => 'manual']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id,
        'domain' => 'manual-accept.com', 'amount' => 9.99, 'billing_cycle' => 'Monthly',
    ]], 'stripe');

    app(OrderService::class)->acceptOrder($order->fresh(), manual: true);

    $service = Service::where('order_id', $order->id)->first();

    expect($order->fresh()->status)->toBe('active')
        ->and($service->status)->toBe('active');
});

test('auto_setup order provisions immediately without payment', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false, 'server_type' => 'custom', 'auto_setup' => 'order']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id,
        'domain' => 'instant-setup.com', 'amount' => 9.99, 'billing_cycle' => 'Monthly',
    ]], 'stripe');

    $service = Service::where('order_id', $order->id)->first();

    expect($service->status)->toBe('active')
        ->and(Invoice::find($order->invoice_id)->status)->toBe('unpaid');
});

// ---------------------------------------------------------------------------
// Bank transfer payment notification flow
// ---------------------------------------------------------------------------

test('approving a payment notification settles the invoice and provisions', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false, 'server_type' => 'custom', 'auto_setup' => 'payment']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id,
        'domain' => 'bt-approve.com', 'amount' => 19.99, 'billing_cycle' => 'Monthly',
    ]], 'banktransfer');

    $invoice = Invoice::find($order->invoice_id);

    $pn = PaymentNotification::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id,
        'gateway' => 'banktransfer', 'sender_name' => 'Test Sender',
        'amount' => (float) $invoice->total, 'transfer_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $result = app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'PN-' . $pn->id, (float) $pn->amount);

    expect($result['status'])->toBe('paid')
        ->and(Service::where('order_id', $order->id)->first()->status)->toBe('active')
        ->and($order->fresh()->status)->toBe('active');
});
