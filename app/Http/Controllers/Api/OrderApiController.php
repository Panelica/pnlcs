<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Services\FraudDetectionService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderApiController extends BaseApiController
{
    public function getOrders(Request $request)
    {
        $query = Order::with('client');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
        }
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        $orders = $query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage());

        return $this->paginated($orders);
    }

    public function addOrder(Request $request)
    {
        $validated = $request->validate([
            'clientid' => 'required|exists:clients,id',
            'paymentmethod' => 'nullable|string',
            'promocode' => 'nullable|string',
        ]);
        $order = Order::create([
            'order_num' => strtoupper(Str::random(10)),
            'client_id' => $validated['clientid'],
            'date' => now()->format('Y-m-d'),
            'promo_code' => $validated['promocode'] ?? null,
            'payment_method' => $validated['paymentmethod'] ?? null,
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        return $this->success(['orderid' => $order->id, 'ordernum' => $order->order_num]);
    }

    /**
     * Accepting, cancelling, holding and deleting all used to be a write to the
     * status column and nothing else, while the same buttons in the admin
     * screen go through OrderService. An integration therefore changed the word
     * on the screen and left the work undone: accepting provisioned nothing,
     * cancelling left the services running and the invoice owing, and calling
     * an order fraudulent left the account serving.
     */
    public function acceptOrder(Request $request, OrderService $orders)
    {
        $order = Order::find($request->orderid);
        if (! $order) {
            return $this->error('Order Not Found', 404);
        }
        $order = $orders->acceptOrder($order, true);

        return $this->success(['orderid' => $order->id, 'status' => $order->status]);
    }

    public function cancelOrder(Request $request, OrderService $orders)
    {
        $order = Order::find($request->orderid);
        if (! $order) {
            return $this->error('Order Not Found', 404);
        }
        $order = $orders->cancelOrder($order);

        return $this->success(['orderid' => $order->id, 'status' => $order->status]);
    }

    /**
     * Putting an order back on hold is a bookkeeping change: there is nothing
     * to undo on a server, so this one stays a status write.
     */
    public function pendingOrder(Request $request)
    {
        $order = Order::find($request->orderid);
        if (! $order) {
            return $this->error('Order Not Found', 404);
        }
        $order->update(['status' => 'pending']);

        return $this->success(['orderid' => $order->id]);
    }

    public function fraudOrder(Request $request, OrderService $orders)
    {
        $order = Order::find($request->orderid);
        if (! $order) {
            return $this->error('Order Not Found', 404);
        }
        $order = $orders->markFraud($order);

        return $this->success(['orderid' => $order->id, 'status' => $order->status]);
    }

    public function deleteOrder(Request $request, OrderService $orders)
    {
        $order = Order::find($request->orderid);
        if (! $order) {
            return $this->error('Order Not Found', 404);
        }
        $orders->deleteOrder($order);

        return $this->success();
    }

    public function orderFraudCheck(Request $request)
    {
        $order = Order::find($request->orderid);
        if (! $order) {
            return $this->error('Order Not Found', 404);
        }

        $fraudService = app(FraudDetectionService::class);
        $result = $fraudService->evaluate($order);

        return $this->success([
            'orderid' => $order->id,
            'fraud' => $result['score'] >= 60,
            'fraud_score' => $result['score'],
            'risk_level' => $result['risk_level'],
            'reasons' => $result['reasons'],
        ]);
    }
}
