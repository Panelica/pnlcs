<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Services\FraudDetectionService;
use App\Services\OrderService;
use Illuminate\Http\Request;

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

    /**
     * Place an order.
     *
     * This used to create an order with nothing on it - no service, no domain,
     * no invoice - and there is no endpoint that could add one afterwards. The
     * integration got back an id for something that could never be provisioned
     * or paid for, and accepting it later did nothing because there was
     * nothing to provision. Orders are now put together by OrderService, the
     * same way the shop does it.
     */
    public function addOrder(Request $request, OrderService $orders)
    {
        $validated = $request->validate([
            'clientid' => 'required|exists:clients,id',
            'paymentmethod' => 'nullable|string',
            'promocode' => 'nullable|string',
            'pid' => 'nullable|array',
            'pid.*' => 'exists:products,id',
            'domain' => 'nullable|array',
            'billingcycle' => 'nullable|array',
            'priceoverride' => 'nullable|array',
        ]);

        $items = $this->orderedItems($request, $validated);

        if ($items === []) {
            return $this->error('An order needs at least one product: send pid[] with a matching billingcycle[].', 422);
        }

        $order = $orders->processOrder(
            Client::findOrFail($validated['clientid']),
            $items,
            $validated['paymentmethod'] ?? 'banktransfer',
            $validated['promocode'] ?? null,
        );

        return $this->success([
            'orderid' => $order->id,
            'ordernum' => $order->order_num,
            'invoiceid' => $order->invoice_id,
        ]);
    }

    /**
     * What was ordered, from the parallel arrays WHMCS-shaped clients send.
     *
     * A price may be given per line; without one the product's own price for
     * that cycle is used, and a cycle the product is not sold on is skipped
     * rather than billed at zero.
     */
    private function orderedItems(Request $request, array $validated): array
    {
        $items = [];

        foreach (($validated['pid'] ?? []) as $index => $productId) {
            $product = Product::find($productId);

            if (! $product) {
                continue;
            }

            $cycle = strtolower((string) ($request->input("billingcycle.{$index}") ?? 'monthly'));
            $override = $request->input("priceoverride.{$index}");
            $amount = $override !== null ? (float) $override : $product->priceFor($cycle);

            if ($amount === null) {
                continue;
            }

            $items[] = [
                'type' => 'service',
                'product_id' => $product->id,
                'domain' => (string) ($request->input("domain.{$index}") ?? ''),
                'billing_cycle' => $cycle,
                'amount' => (float) $amount,
            ];
        }

        return $items;
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
    public function pendingOrder(Request $request, OrderService $orders)
    {
        $order = Order::find($request->orderid);
        if (! $order) {
            return $this->error('Order Not Found', 404);
        }

        // Through the service: clearing a fraud alarm or a cancellation must
        // take the stock unit the verdict handed back, or the pair of moves
        // mints stock. A bare status write did neither.
        $order = $orders->reopenOrder($order);
        if ($order->status !== 'pending') {
            $order->update(['status' => 'pending']);
        }

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
