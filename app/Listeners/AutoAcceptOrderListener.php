<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\InvoicePaid;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;

class AutoAcceptOrderListener
{
    public function __construct(private OrderService $orderService) {}

    public function handleInvoicePaid(InvoicePaid $event): void
    {
        $orders = Order::where('invoice_id', $event->invoice->id)
            ->where('status', OrderStatus::Pending->value)
            ->get();

        foreach ($orders as $order) {
            try {
                $this->orderService->acceptOrder($order);
                Log::info("AutoAcceptOrder: order #{$order->id} accepted after invoice #{$event->invoice->id} paid");
            } catch (\Throwable $e) {
                Log::error("AutoAcceptOrder failed for order #{$order->id}: " . $e->getMessage());
            }
        }
    }
}
