<?php

use App\Events\ServiceTerminated;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;

/*
 * Terminating the service of a live order gave its unit nothing back: a
 * product with one VPS left stayed at 0 after that VPS was cancelled and
 * terminated, and could not be sold again. OrderService::restockOrder()
 * already counts terminated services as returned.
 */

function restockService(bool $control, int $qty, string $orderStatus = 'active'): Service
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'stock_control' => $control, 'stock_qty' => $qty,
    ]);
    $client = Client::factory()->create();
    $order = Order::factory()->create(['client_id' => $client->id, 'status' => $orderStatus]);

    return Service::factory()->create([
        'client_id' => $client->id, 'order_id' => $order->id, 'product_id' => $product->id, 'status' => 'terminated',
    ]);
}

test('a terminated service hands its unit back', function () {
    $service = restockService(control: true, qty: 0);

    event(new ServiceTerminated($service));

    expect($service->product->fresh()->stock_qty)->toBe(1)
        ->and($service->product->fresh()->outOfStock())->toBeFalse();
});

test('a product without stock control is left alone', function () {
    $service = restockService(control: false, qty: 0);

    event(new ServiceTerminated($service));

    expect($service->product->fresh()->stock_qty)->toBe(0);
});

test('a service of a cancelled or fraud order is not counted twice', function (string $status) {
    // Its unit went back when the order was cancelled, before the server
    // account was terminated.
    $service = restockService(control: true, qty: 3, orderStatus: $status);

    event(new ServiceTerminated($service));

    expect($service->product->fresh()->stock_qty)->toBe(3);
})->with(['cancelled', 'fraud']);

test('the daily cancellation run gives the unit back', function () {
    $service = restockService(control: true, qty: 0);
    $service->update(['status' => 'active']);
    \App\Models\CancellationRequest::create([
        'service_id' => $service->id, 'type' => 'Immediate', 'reason' => 'Not needed',
    ]);

    $this->artisan('pnlcs:process-cancellations')->assertExitCode(0);

    expect($service->fresh()->status)->toBeIn(['terminated', 'cancelled'])
        ->and($service->product->fresh()->stock_qty)->toBe(1);
});
