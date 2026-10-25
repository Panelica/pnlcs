<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Setting;
use App\Services\OrderService;

/*
 * Releasing an order held as fraud changed the order's status and nothing
 * else. The verdict had cancelled its invoice and suspended its services, so
 * the released order sat pending with an invoice nobody could pay and a
 * service still switched off - the false alarm could not be undone short of
 * re-ordering. Releasing it now puts back what the verdict took.
 */

function rorOrder(): array
{
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 20, 'due_date' => now()->subDays(3)]);
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => 'pending', 'invoice_id' => $invoice->id, 'order_num' => strtoupper(substr(md5(microtime()), 0, 8))]);

    return [$client, $order, $invoice];
}

test('releasing a fraud order makes its invoice payable again and switches its services back on', function () {
    Setting::set('InvoiceDueDays', '7');
    [$client, $order, $invoice] = rorOrder();
    $service = Service::factory()->create(['client_id' => $client->id, 'order_id' => $order->id, 'server_id' => null, 'status' => 'active']);
    $other = Service::factory()->create(['client_id' => $client->id, 'order_id' => $order->id, 'server_id' => null, 'status' => 'suspended', 'suspension_reason' => 'Abuse report']);
    $orders = app(OrderService::class);

    $orders->markFraud($order);
    expect($invoice->fresh()->status)->toBe('cancelled')->and($service->fresh()->status)->toBe('suspended');

    $orders->reopenOrder($order->fresh());

    expect($order->fresh()->status)->toBe('pending')
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and($invoice->fresh()->due_date->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($service->fresh()->status)->toBe('active')
        ->and($service->fresh()->suspension_reason)->toBeNull()
        // Suspended for something else: left as it is.
        ->and($other->fresh()->status)->toBe('suspended');
});

test('an invoice paid before the verdict is left paid', function () {
    [, $order, $invoice] = rorOrder();
    $orders = app(OrderService::class);
    $orders->markFraud($order);
    $invoice->update(['status' => 'paid']);

    $orders->reopenOrder($order->fresh());

    expect($invoice->fresh()->status)->toBe('paid');
});

test('a reopened cancelled order gets its invoice back too', function () {
    [, $order, $invoice] = rorOrder();
    $orders = app(OrderService::class);
    $orders->cancelOrder($order);
    expect($invoice->fresh()->status)->toBe('cancelled');

    $orders->reopenOrder($order->fresh());

    expect($invoice->fresh()->status)->toBe('unpaid');
});
