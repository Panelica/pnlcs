<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with("client");
        if ($request->filled("status")) { $query->where("status", $request->status); }
        $orders = $query->orderBy("created_at", "desc")->paginate(25);
        return view("admin.orders.index", compact("orders"));
    }

    public function show(Order $order)
    {
        $order->load("client", "services", "domains", "invoice");
        return view("admin.orders.show", compact("order"));
    }
}
