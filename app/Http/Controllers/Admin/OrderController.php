<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request): View
    {
        $query = Order::with('client');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        $order->load('client', 'services', 'domains', 'invoice');

        return view('admin.orders.show', compact('order'));
    }

    /**
     * Accept a pending order and activate its services.
     */
    public function accept(Order $order): RedirectResponse
    {
        if ($order->status === 'Active') {
            return back()->with('info', 'Order is already active.');
        }

        if (!in_array($order->status, ['Pending'])) {
            return back()->with('error', __('admin.messages.order_pending_error', ['status' => $order->status]));
        }

        $this->orderService->acceptOrder($order);

        return back()->with('success', __('admin.messages.order_accepted', ['num' => $order->order_num]));
    }

    /**
     * Cancel an order and terminate related services.
     */
    public function cancel(Order $order): RedirectResponse
    {
        if (in_array($order->status, ['Cancelled', 'Fraud'])) {
            return back()->with('info', 'Order is already cancelled.');
        }

        $this->orderService->cancelOrder($order);

        return back()->with('success', __('admin.messages.order_cancelled', ['num' => $order->order_num]));
    }

    /**
     * Mark an order as fraud and suspend related services.
     */
    public function markFraud(Order $order): RedirectResponse
    {
        if ($order->status === 'Fraud') {
            return back()->with('info', 'Order is already marked as fraud.');
        }

        $this->orderService->markFraud($order);

        return back()->with('success', __('admin.messages.order_fraud', ['num' => $order->order_num]));
    }

    /**
     * Delete (soft-cancel) an order and all related items.
     */
    public function delete(Order $order): RedirectResponse
    {
        $orderNum = $order->order_num;

        $this->orderService->deleteOrder($order);

        return redirect()
            ->route('admin.orders.index')
            ->with('success', __('admin.messages.order_deleted', ['num' => $orderNum]));
    }
}
