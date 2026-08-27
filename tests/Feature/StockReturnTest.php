<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\OrderService;

/**
 * Stock was a one-way counter: taken at the order, never handed back. A
 * cancelled or fraud-held order burned the unit for good, so a limited
 * product sold out from orders that never became sales - and a run of junk
 * orders could empty the shelf without paying for anything. The other
 * direction matters too: clearing a false fraud alarm (back to pending)
 * must take the unit again, or the pair of moves mints stock.
 */
function stockedOrder(int $qty = 5): array
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
        'stock_control' => true,
        'stock_qty' => $qty,
    ]);
    $client = Client::factory()->create();

    $order = app(OrderService::class)->processOrder($client, [[
        'product_id' => $product->id,
        'billing_cycle' => 'Monthly',
        'price' => 10.0,
    ]], 'banktransfer');

    return [$order, $product];
}

it('takes a unit when the order is placed', function () {
    [, $product] = stockedOrder(5);

    expect((int) $product->fresh()->stock_qty)->toBe(4);
});

it('hands the unit back when the order is cancelled', function () {
    [$order, $product] = stockedOrder(5);

    app(OrderService::class)->cancelOrder($order);

    expect((int) $product->fresh()->stock_qty)->toBe(5);
});

it('hands the unit back only once however often cancel runs', function () {
    [$order, $product] = stockedOrder(5);

    $service = app(OrderService::class);
    $service->cancelOrder($order->fresh());
    $service->cancelOrder($order->fresh());

    expect((int) $product->fresh()->stock_qty)->toBe(5);
});

it('hands the unit back when the order is held as fraud', function () {
    [$order, $product] = stockedOrder(5);

    app(OrderService::class)->markFraud($order);

    expect((int) $product->fresh()->stock_qty)->toBe(5);
});

it('hands the unit back only once however often fraud runs', function () {
    [$order, $product] = stockedOrder(5);

    $service = app(OrderService::class);
    $service->markFraud($order->fresh());
    $service->markFraud($order->fresh());

    expect((int) $product->fresh()->stock_qty)->toBe(5);
});

it('takes the unit again when a fraud verdict is cleared back to pending', function () {
    [$order, $product] = stockedOrder(5);

    $service = app(OrderService::class);
    $service->markFraud($order->fresh());
    $service->reopenOrder($order->fresh());

    expect((int) $product->fresh()->stock_qty)->toBe(4);
});

it('still reopens when the shelf has emptied meanwhile, without going negative', function () {
    [$order, $product] = stockedOrder(1);

    $service = app(OrderService::class);
    $service->markFraud($order->fresh());
    $product->fresh()->update(['stock_qty' => 0]);

    $reopened = $service->reopenOrder($order->fresh());

    expect(strtolower((string) $reopened->status))->toBe('pending')
        ->and((int) $product->fresh()->stock_qty)->toBe(0);
});
