<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\ServiceTerminated;
use App\Models\Product;

/**
 * A terminated service hands its unit back to a stock-controlled product.
 *
 * Ordering takes one unit off stock_qty, and cancelling or holding an order as
 * fraud gives it back (OrderService::restockOrder()). Terminating the service
 * of a live order gave nothing back, although restockOrder() already counts
 * terminated services as returned: a product with one VPS left stayed at 0
 * after that VPS was cancelled and terminated, and could not be sold again.
 *
 * A service whose order was cancelled or held as fraud is skipped: its unit
 * went back when the order was, before the server account was terminated.
 */
class RestockOnTermination
{
    public function handle(ServiceTerminated $event): void
    {
        $service = $event->service;
        $product = $service->product;

        if (! $product || ! $product->stock_control) {
            return;
        }

        $orderStatus = strtolower((string) $service->order?->status);
        if (in_array($orderStatus, [OrderStatus::Cancelled->value, OrderStatus::Fraud->value], true)) {
            return;
        }

        Product::whereKey($product->id)->increment('stock_qty');
    }
}
