<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Transaction;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        $todayIncome = Transaction::whereDate("date", $today)->sum("amount_in");
        $weekIncome  = Transaction::where("date", ">=", $startOfWeek)->sum("amount_in");
        $monthIncome = Transaction::where("date", ">=", $startOfMonth)->sum("amount_in");

        return view("admin.dashboard", [
            "totalClients"   => Client::count(),
            "activeClients"  => Client::where("status", "active")->count(),
            "totalAdmins"    => Admin::count(),
            "pendingOrders"  => Order::where("status", "pending")->count(),
            "openTickets"    => Ticket::where("status", "open")->count(),
            "unpaidInvoices" => Invoice::where("status", "unpaid")->count(),
            "activeServices" => Service::where("status", "active")->count(),
            "activeDomains"  => Domain::where("status", "active")->count(),
            "totalRevenue"   => Invoice::where("status", "paid")->sum("total"),
            "todayIncome"    => $todayIncome,
            "weekIncome"     => $weekIncome,
            "monthIncome"    => $monthIncome,
            "recentClients"  => Client::orderBy("created_at", "desc")->take(5)->get(),
            "recentTickets"  => Ticket::with("department")->orderBy("created_at", "desc")->take(5)->get(),
            "recentOrders"   => Order::with("client")->orderBy("created_at", "desc")->take(5)->get(),
        ]);
    }
}
