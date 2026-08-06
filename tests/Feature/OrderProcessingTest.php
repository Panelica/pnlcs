<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Service;
use App\Services\OrderService;

// Helper to build the service via DI container (resolves all dependencies)
function makeOrderService(): OrderService
{
    return app(OrderService::class);
}

// ---------------------------------------------------------------------------
// Process order
// ---------------------------------------------------------------------------

test('process order creates an order record', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false]);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        [
            'type' => 'service',
            'product_id' => $product->id,
            'domain' => 'example.com',
            'amount' => 9.99,
            'billing_cycle' => 'Monthly',
        ],
    ], 'banktransfer');

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->client_id)->toBe($client->id)
        ->and($order->status)->toBe('pending');
});

test('process order creates services for service items', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    // Setup on payment: the service must wait for the invoice to be settled.
    $product = Product::factory()->create(['tax' => false, 'auto_setup' => 'payment']);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        [
            'type' => 'service',
            'product_id' => $product->id,
            'domain' => 'mysite.com',
            'amount' => 9.99,
            'billing_cycle' => 'Monthly',
        ],
    ], 'banktransfer');

    $createdServices = Service::where('order_id', $order->id)->get();

    expect($createdServices)->toHaveCount(1)
        ->and($createdServices->first()->product_id)->toBe($product->id)
        ->and($createdServices->first()->status)->toBe('pending');
});

test('a product set up on order placement is provisioned before payment', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    // auto_setup = order with the Custom (manual) module, which reports success
    // without contacting anything, so placing the order activates the service.
    $product = Product::factory()->create([
        'tax' => false,
        'auto_setup' => 'order',
        'server_type' => 'custom',
    ]);

    $order = makeOrderService()->processOrder($client, [
        [
            'type' => 'service',
            'product_id' => $product->id,
            'domain' => 'setup-on-order.com',
            'amount' => 9.99,
            'billing_cycle' => 'Monthly',
        ],
    ], 'banktransfer');

    $created = Service::where('order_id', $order->id)->firstOrFail();

    expect($created->status)->toBe('active')
        ->and($created->registration_date)->not->toBeNull()
        // The invoice is still waiting: activation here is deliberate, not a
        // payment being skipped.
        ->and(Invoice::find($order->invoice_id)->status)->toBe('unpaid');
});

test('process order creates domains for domain items', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        [
            'type' => 'domain',
            'domain' => 'newdomain.com',
            'amount' => 12.00,
            'domain_type' => 'register',
        ],
    ], 'banktransfer');

    $createdDomains = Domain::where('order_id', $order->id)->get();

    expect($createdDomains)->toHaveCount(1)
        ->and($createdDomains->first()->domain)->toBe('newdomain.com')
        ->and($createdDomains->first()->status)->toBe('pending');
});

test('process order generates an invoice', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false]);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        [
            'type' => 'service',
            'product_id' => $product->id,
            'domain' => 'invoice-test.com',
            'amount' => 19.99,
            'billing_cycle' => 'Monthly',
        ],
    ], 'banktransfer');

    expect($order->invoice_id)->not->toBeNull();

    $invoice = Invoice::find($order->invoice_id);
    expect($invoice)->not->toBeNull()
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->status)->toBe('unpaid');
});

test('process order calculates invoice total from items', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false]);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        ['type' => 'service', 'product_id' => $product->id, 'domain' => 'a.com', 'amount' => 10.00, 'billing_cycle' => 'Monthly'],
        ['type' => 'domain',  'domain' => 'b.com', 'amount' => 5.00, 'domain_type' => 'register'],
    ], 'banktransfer');

    $invoice = Invoice::find($order->invoice_id);

    expect((float) $invoice->subtotal)->toBe(15.00)
        ->and((float) $invoice->total)->toBe(15.00);
});

test('process order applies valid promotion code', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false]);

    $promo = Promotion::factory()->create([
        'code' => 'SAVE10',
        'type' => 'fixed',
        'value' => 10.00,
        'max_uses' => 0,
        'uses' => 0,
    ]);

    $service = makeOrderService();

    $order = $service->processOrder($client, [
        ['type' => 'service', 'product_id' => $product->id, 'domain' => 'promo.com', 'amount' => 50.00, 'billing_cycle' => 'Monthly'],
    ], 'banktransfer', 'SAVE10');

    $invoice = Invoice::find($order->invoice_id);

    // The discount is a line of its own; credit is money the customer paid in.
    expect((float) InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Discount')->sum('amount'))->toBe(-10.00)
        ->and((float) $invoice->credit)->toBe(0.00)
        ->and((float) $invoice->total)->toBe(40.00);
});

// ---------------------------------------------------------------------------
// Accept order
// ---------------------------------------------------------------------------

test('accept order changes status to Active', function () {
    $client = Client::factory()->create();
    $service = makeOrderService();

    $order = Order::factory()->pending()->create(['client_id' => $client->id]);

    $updated = $service->acceptOrder($order);

    expect($updated->status)->toBe('active');
});

test('accept order activates pending services', function () {
    $client = Client::factory()->create();
    $product = Product::factory()->create();
    $service = makeOrderService();

    $order = Order::factory()->pending()->create(['client_id' => $client->id]);
    $svc = Service::factory()->pending()->create(['order_id' => $order->id, 'client_id' => $client->id, 'product_id' => $product->id]);

    $service->acceptOrder($order);

    expect($svc->fresh()->status)->toBe('active')
        ->and($svc->fresh()->registration_date)->not->toBeNull();
});

test('accept order is idempotent for already-active orders', function () {
    $client = Client::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => 'active']);

    $updated = makeOrderService()->acceptOrder($order);

    expect($updated->status)->toBe('active');
});

// ---------------------------------------------------------------------------
// Cancel order
// ---------------------------------------------------------------------------

test('cancel order changes status to Cancelled', function () {
    $client = Client::factory()->create();
    $order = Order::factory()->pending()->create(['client_id' => $client->id]);

    makeOrderService()->cancelOrder($order);

    expect($order->fresh()->status)->toBe('cancelled');
});

test('cancel order terminates active services', function () {
    $client = Client::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $svc = Service::factory()->active()->create(['order_id' => $order->id, 'client_id' => $client->id, 'product_id' => $product->id]);

    makeOrderService()->cancelOrder($order);

    expect($svc->fresh()->status)->toBe('cancelled');
});

test('cancel order also cancels unpaid invoice', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid']);
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => 'pending', 'invoice_id' => $invoice->id]);

    makeOrderService()->cancelOrder($order);

    expect($invoice->fresh()->status)->toBe('cancelled');
});

test('cancel order does not cancel paid invoice', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $invoice = Invoice::factory()->paid()->create(['client_id' => $client->id]);
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => 'active', 'invoice_id' => $invoice->id]);

    makeOrderService()->cancelOrder($order);

    expect($invoice->fresh()->status)->toBe('paid');
});

// ---------------------------------------------------------------------------
// Mark fraud
// ---------------------------------------------------------------------------

test('mark fraud changes order status to Fraud', function () {
    $client = Client::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => 'active']);

    makeOrderService()->markFraud($order);

    expect($order->fresh()->status)->toBe('fraud');
});

test('mark fraud suspends active services', function () {
    $client = Client::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    $svc = Service::factory()->active()->create(['order_id' => $order->id, 'client_id' => $client->id, 'product_id' => $product->id]);

    makeOrderService()->markFraud($order);

    expect($svc->fresh()->status)->toBe('suspended')
        ->and($svc->fresh()->suspension_reason)->toBe('Order marked as fraud');
});

test('mark fraud records fraud output', function () {
    $client = Client::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => 'active']);

    makeOrderService()->markFraud($order);

    expect($order->fresh()->fraud_module)->toBe('manual')
        ->and($order->fresh()->fraud_output)->toContain('Manually marked as fraud');
});

// ---------------------------------------------------------------------------
// Delete order
// ---------------------------------------------------------------------------

test('delete order soft-deletes the order record', function () {
    $client = Client::factory()->create();
    $order = Order::factory()->cancelled()->create(['client_id' => $client->id]);

    $orderId = $order->id;
    makeOrderService()->deleteOrder($order);

    expect(Order::find($orderId))->toBeNull();
});
