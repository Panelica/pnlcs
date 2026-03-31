<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $reports = [
            ["name" => "Income Summary", "slug" => "income-summary", "category" => "Financial", "description" => "Monthly income breakdown"],
            ["name" => "Annual Income Report", "slug" => "annual-income", "category" => "Financial", "description" => "Yearly income overview"],
            ["name" => "Income by Product", "slug" => "income-by-product", "category" => "Financial", "description" => "Revenue per product/service"],
            ["name" => "Transactions List", "slug" => "transactions", "category" => "Financial", "description" => "All transaction records"],
            ["name" => "New Customers", "slug" => "new-customers", "category" => "Client", "description" => "New client registrations over time"],
            ["name" => "Clients by Country", "slug" => "clients-by-country", "category" => "Client", "description" => "Client distribution by country"],
            ["name" => "Top 10 Clients by Income", "slug" => "top-clients", "category" => "Client", "description" => "Highest revenue generating clients"],
            ["name" => "Active Services", "slug" => "active-services", "category" => "Service", "description" => "All active services overview"],
            ["name" => "Domain Overview", "slug" => "domains-overview", "category" => "Domain", "description" => "Domain registration statistics"],
            ["name" => "Support Ticket Volume", "slug" => "ticket-volume", "category" => "Support", "description" => "Ticket volume over time"],
        ];
        return view("admin.reports.index", compact("reports"));
    }

    public function show(string $slug)
    {
        return match($slug) {
            "income-summary" => $this->incomeSummary(),
            "new-customers" => $this->newCustomers(),
            "active-services" => $this->activeServices(),
            "top-clients" => $this->topClients(),
            default => back()->with("error", "Report not found"),
        };
    }

    private function incomeSummary()
    {
        $data = Transaction::selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(amount_in) as income, SUM(amount_out) as refunds")
            ->groupBy("month")->orderBy("month", "desc")->take(12)->get();
        return view("admin.reports.show", ["title" => "Income Summary", "data" => $data, "columns" => ["Month", "Income", "Refunds"]]);
    }

    private function newCustomers()
    {
        $data = Client::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy("month")->orderBy("month", "desc")->take(12)->get();
        return view("admin.reports.show", ["title" => "New Customers", "data" => $data, "columns" => ["Month", "New Clients"]]);
    }

    private function activeServices()
    {
        $data = Service::with("product", "client")->where("status", "active")->orderBy("id", "desc")->take(100)->get();
        return view("admin.reports.show", ["title" => "Active Services", "data" => $data, "columns" => ["ID", "Client", "Product", "Domain", "Amount"]]);
    }

    private function topClients()
    {
        $data = Invoice::where("status", "paid")->selectRaw("client_id, SUM(total) as revenue")
            ->groupBy("client_id")->orderBy("revenue", "desc")->take(10)->with("client")->get();
        return view("admin.reports.show", ["title" => "Top 10 Clients by Income", "data" => $data, "columns" => ["Client", "Revenue"]]);
    }
}
