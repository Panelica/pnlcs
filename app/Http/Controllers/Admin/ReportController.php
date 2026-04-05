<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Domain;
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
            "income-summary"     => $this->incomeSummary(),
            "annual-income"      => $this->annualIncome(),
            "income-by-product"  => $this->incomeByProduct(),
            "transactions"       => $this->transactionsList(),
            "new-customers"      => $this->newCustomers(),
            "clients-by-country" => $this->clientsByCountry(),
            "top-clients"        => $this->topClients(),
            "active-services"    => $this->activeServices(),
            "domains-overview"   => $this->domainsOverview(),
            "ticket-volume"      => $this->ticketVolume(),
            default              => back()->with("error", __("admin.messages.report_not_found")),
        };
    }

    private function incomeSummary()
    {
        $data = Transaction::selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(amount_in) as income, SUM(amount_out) as refunds")
            ->groupBy("month")->orderBy("month", "desc")->take(12)->get();
        return view("admin.reports.show", ["title" => "Income Summary", "data" => $data, "columns" => ["Month", "Income", "Refunds"]]);
    }

    private function annualIncome()
    {
        $data = Transaction::selectRaw("YEAR(date) as year, SUM(amount_in) as income, SUM(amount_out) as refunds, (SUM(amount_in) - SUM(amount_out)) as net")
            ->groupBy("year")->orderBy("year", "desc")->get();
        return view("admin.reports.show", ["title" => "Annual Income Report", "data" => $data, "columns" => ["Year", "Income", "Refunds", "Net"]]);
    }

    private function incomeByProduct()
    {
        $data = Service::selectRaw("COALESCE(products.name, 'Unknown') as product_name, COUNT(services.id) as total_services, SUM(services.amount) as total_revenue")
            ->leftJoin("products", "services.product_id", "=", "products.id")
            ->groupBy("products.name")->orderBy("total_revenue", "desc")->get();
        return view("admin.reports.show", ["title" => "Income by Product", "data" => $data, "columns" => ["Product", "Services", "Total Revenue"]]);
    }

    private function transactionsList()
    {
        $data = Transaction::with("client")->orderBy("date", "desc")->paginate(50);
        return view("admin.reports.transactions", ["title" => "Transactions", "data" => $data]);
    }

    private function newCustomers()
    {
        $data = Client::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy("month")->orderBy("month", "desc")->take(12)->get();
        return view("admin.reports.show", ["title" => "New Customers", "data" => $data, "columns" => ["Month", "New Clients"]]);
    }

    private function clientsByCountry()
    {
        $data = Client::selectRaw("COALESCE(NULLIF(TRIM(country), ''), 'Unknown') as country_name, COUNT(*) as count")
            ->groupBy("country_name")->orderBy("count", "desc")->get();
        return view("admin.reports.show", ["title" => "Clients by Country", "data" => $data, "columns" => ["Country", "Clients"]]);
    }

    private function topClients()
    {
        $data = Invoice::where("status", "paid")->selectRaw("client_id, SUM(total) as revenue")
            ->groupBy("client_id")->orderBy("revenue", "desc")->take(10)->with("client")->get();
        return view("admin.reports.show", ["title" => "Top 10 Clients by Income", "data" => $data, "columns" => ["Client", "Revenue"]]);
    }

    private function activeServices()
    {
        $data = Service::with("product", "client")->where("status", "active")->orderBy("id", "desc")->take(100)->get();
        return view("admin.reports.show", ["title" => "Active Services", "data" => $data, "columns" => ["ID", "Client", "Product", "Domain", "Amount"]]);
    }

    private function domainsOverview()
    {
        $data = Domain::selectRaw("status, COUNT(*) as count")
            ->groupBy("status")->orderBy("count", "desc")->get();
        return view("admin.reports.show", ["title" => "Domains Overview", "data" => $data, "columns" => ["Status", "Count"]]);
    }

    private function ticketVolume()
    {
        $data = Ticket::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total, SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count, SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count")
            ->groupBy("month")->orderBy("month", "desc")->take(12)->get();
        return view("admin.reports.show", ["title" => "Support Ticket Volume", "data" => $data, "columns" => ["Month", "Total", "Open", "Closed"]]);
    }
}
