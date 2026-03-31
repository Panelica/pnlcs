<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Service;
use App\Services\InvoiceService;
use App\Services\InvoiceGenerationService;
use App\Services\OrderService;


// Helper to build the service
function makeOrderService(): OrderService
{
    $invoiceService = app(InvoiceService::class);
    $generationService = app(InvoiceGenerationService::class);
    return new OrderService($invoiceService, $generationService);
}

// ---------------------------------------------------------------------------
// Process order
// ---------------------------------------------------------------------------

test('process order creates an order record', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false]);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        [
            'type'       => 'service',
            'product_id' => $product->id,
            'domain'     => 'example.com',
            'amount'     => 9.99,
            'billing_cycle' => 'Monthly',
        ],
    ], 'banktransfer');

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->client_id)->toBe($client->id)
        ->and($order->status)->toBe('Pending');
});

test('process order creates services for service items', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false]);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        [
            'type'          => 'service',
            'product_id'    => $product->id,
            'domain'        => 'mysite.com',
            'amount'        => 9.99,
            'billing_cycle' => 'Monthly',
        ],
    ], 'banktransfer');

    $createdServices = Service::where('order_id', $order->id)->get();

    expect($createdServices)->toHaveCount(1)
        ->and($createdServices->first()->product_id)->toBe($product->id)
        ->and($createdServices->first()->status)->toBe('Pending');
});

test('process order creates domains for domain items', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        [
            'type'        => 'domain',
            'domain'      => 'newdomain.com',
            'amount'      => 12.00,
            'domain_type' => 'register',
        ],
    ], 'banktransfer');

    $createdDomains = Domain::where('order_id', $order->id)->get();

    expect($createdDomains)->toHaveCount(1)
        ->and($createdDomains->first()->domain)->toBe('newdomain.com')
        ->and($createdDomains->first()->status)->toBe('Pending');
});

test('process order generates an invoice', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false]);
    $service = makeOrderService();

    $order = $service->processOrder($client, [
        [
            'type'          => 'service',
            'product_id'    => $product->id,
            'domain'        => 'invoice-test.com',
            'amount'        => 19.99,
            'billing_cycle' => 'Monthly',
        ],
    ], 'banktransfer');

    expect($order->invoice_id)->not->toBeNull();

    $invoice = Invoice::find($order->invoice_id);
    expect($invoice)->not->toBeNull()
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->status)->toBe('Unpaid');
});

test('process order calculates invoice total from items', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
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
        'code'     => 'SAVE10',
        'type'     => 'fixed',
        'value'    => 10.00,
        'max_uses' => 0,
        'uses'     => 0,
    ]);

    $service = makeOrderService();

    $order = $service->processOrder($client, [
        ['type' => 'service', 'product_id' => $product->id, 'domain' => 'promo.com', 'amount' => 50.00, 'billing_cycle' => 'Monthly'],
    ], 'banktransfer', 'SAVE10');

    $invoice = Invoice::find($order->invoice_id);

    expect((float) $invoice->credit)->toBe(10.00)
        ->and((float) $invoice->total)->toBe(40.00);
});

// ---------------------------------------------------------------------------
// Accept order
// ---------------------------------------------------------------------------

test('accept order changes status to Active', function () {
    $client  = Client::factory()->create();
    $service = makeOrderService();

    $order = Order::factory()->pending()->create(['client_id' => $client->id]);

    $updated = $service->acceptOrder($order);

    expect($updated->status)->toBe('Active');
});

test('accept order activates pending services', function () {
    $client  = Client::factory()->create();
    $product = Product::factory()->create();
    $service = makeOrderService();

    $order = Order::factory()->pending()->create(['client_id' => $client->id]);
    $svc   = Service::factory()->pending()->create(['order_id' => $order->id, 'client_id' => $client->id, 'product_id' => $product->id]);

    $service->acceptOrder($order);

    expect($svc->fresh()->status)->toBe('Active')
        ->and($svc->fresh()->registration_date)->not->toBeNull();
});

test('accept order is idempotent for already-active orders', function () {
    $client = Client::factory()->create();
    $order  = Order::factory()->create(['client_id' => $client->id, 'status' => 'Active']);

    $updated = makeOrderService()->acceptOrder($order);

    expect($updated->status)->toBe('Active');
});

// ---------------------------------------------------------------------------
// Cancel order
// ---------------------------------------------------------------------------

test('cancel order changes status to Cancelled', function () {
    $client = Client::factory()->create();
    $order  = Order::factory()->pending()->create(['client_id' => $client->id]);

    makeOrderService()->cancelOrder($order);

    expect($order->fresh()->status)->toBe('Cancelled');
});

test('cancel order terminates active services', function () {
    $client  = Client::factory()->create();
    $product = Product::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id, 'status' => 'Active']);
    $svc     = Service::factory()->active()->create(['order_id' => $order->id, 'client_id' => $client->id, 'product_id' => $product->id]);

    makeOrderService()->cancelOrder($order);

    expect($svc->fresh()->status)->toBe('Cancelled');
});

test('cancel order also cancels unpaid invoice', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'Unpaid']);
    $order   = Order::factory()->create(['client_id' => $client->id, 'status' => 'Pending', 'invoice_id' => $invoice->id]);

    makeOrderService()->cancelOrder($order);

    expect($invoice->fresh()->status)->toBe('Cancelled');
});

test('cancel order does not cancel paid invoice', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $invoice = Invoice::factory()->paid()->create(['client_id' => $client->id]);
    $order   = Order::factory()->create(['client_id' => $client->id, 'status' => 'Active', 'invoice_id' => $invoice->id]);

    makeOrderService()->cancelOrder($order);

    expect($invoice->fresh()->status)->toBe('Paid');
});

// ---------------------------------------------------------------------------
// Mark fraud
// ---------------------------------------------------------------------------

test('mark fraud changes order status to Fraud', function () {
    $client = Client::factory()->create();
    $order  = Order::factory()->create(['client_id' => $client->id, 'status' => 'Active']);

    makeOrderService()->markFraud($order);

    expect($order->fresh()->status)->toBe('Fraud');
});

test('mark fraud suspends active services', function () {
    $client  = Client::factory()->create();
    $product = Product::factory()->create();
    $order   = Order::factory()->create(['client_id' => $client->id, 'status' => 'Active']);
    $svc     = Service::factory()->active()->create(['order_id' => $order->id, 'client_id' => $client->id, 'product_id' => $product->id]);

    makeOrderService()->markFraud($order);

    expect($svc->fresh()->status)->toBe('Suspended')
        ->and($svc->fresh()->suspension_reason)->toBe('Order marked as fraud');
});

test('mark fraud records fraud output', function () {
    $client = Client::factory()->create();
    $order  = Order::factory()->create(['client_id' => $client->id, 'status' => 'Active']);

    makeOrderService()->markFraud($order);

    expect($order->fresh()->fraud_module)->toBe('manual')
        ->and($order->fresh()->fraud_output)->toContain('Manually marked as fraud');
});

// ---------------------------------------------------------------------------
// Delete order
// ---------------------------------------------------------------------------

test('delete order soft-deletes the order record', function () {
    $client = Client::factory()->create();
    $order  = Order::factory()->cancelled()->create(['client_id' => $client->id]);

    $orderId = $order->id;
    makeOrderService()->deleteOrder($order);

    expect(Order::find($orderId))->toBeNull();
});
