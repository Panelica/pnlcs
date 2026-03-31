<?php
namespace App\Http\Controllers\Api;

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Ticket;

class SystemApiController extends BaseApiController
{
    public function getStats()
    {
        return $this->success([
            "stats" => [
                "total_clients" => Client::count(),
                "active_clients" => Client::where("status", "active")->count(),
                "total_services" => Service::count(),
                "active_services" => Service::where("status", "active")->count(),
                "total_domains" => Domain::count(),
                "total_invoices" => Invoice::count(),
                "unpaid_invoices" => Invoice::where("status", "unpaid")->count(),
                "total_orders" => Order::count(),
                "pending_orders" => Order::where("status", "pending")->count(),
                "total_tickets" => Ticket::count(),
                "open_tickets" => Ticket::where("status", "open")->count(),
                "total_admins" => Admin::count(),
            ],
        ]);
    }

    public function getHealthStatus()
    {
        return $this->success([
            "health" => [
                "status" => "ok",
                "version" => "1.0.0",
                "laravel" => app()->version(),
                "php" => phpversion(),
            ],
        ]);
    }

    public function pnlcsDetails()
    {
        return $this->success([
            "pnlcs" => [
                "version" => "1.0.0",
                "company_name" => Setting::get("CompanyName", "PNLCS"),
            ],
        ]);
    }
}
