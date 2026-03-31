<?php
namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderApiController extends BaseApiController
{
    public function getOrders(Request $request)
    {
        $query = Order::with("client");
        if ($request->filled("status")) { $query->where("status", $request->status); }
        if ($request->filled("userid")) { $query->where("client_id", $request->userid); }
        $orders = $query->orderBy("id", "desc")->paginate($request->get("limitnum", 25));
        return $this->paginated($orders);
    }

    public function addOrder(Request $request)
    {
        $validated = $request->validate([
            "clientid" => "required|exists:clients,id",
            "paymentmethod" => "nullable|string",
            "promocode" => "nullable|string",
        ]);
        $order = Order::create([
            "order_num" => strtoupper(Str::random(10)),
            "client_id" => $validated["clientid"],
            "date" => now()->format("Y-m-d"),
            "promo_code" => $validated["promocode"] ?? null,
            "payment_method" => $validated["paymentmethod"] ?? null,
            "status" => "pending",
            "ip_address" => $request->ip(),
        ]);
        return $this->success(["orderid" => $order->id, "ordernum" => $order->order_num]);
    }

    public function acceptOrder(Request $request)
    {
        $order = Order::find($request->orderid);
        if (!$order) return $this->error("Order Not Found", 404);
        $order->update(["status" => "active"]);
        return $this->success(["orderid" => $order->id]);
    }

    public function cancelOrder(Request $request)
    {
        $order = Order::find($request->orderid);
        if (!$order) return $this->error("Order Not Found", 404);
        $order->update(["status" => "cancelled"]);
        return $this->success(["orderid" => $order->id]);
    }
}
