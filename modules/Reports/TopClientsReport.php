<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TopClientsReport extends AbstractReport
{
    public function getTitle(): string { return 'Top 10 Clients by Income'; }
    public function getDescription(): string { return 'Highest revenue generating clients'; }
    public function getCategory(): string { return 'Client'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("invoices")
            ->join("clients", "clients.id", "=", "invoices.client_id")
            ->selectRaw("clients.id, CONCAT(clients.first_name, ' ', clients.last_name) as client, clients.email, clients.company_name, COUNT(invoices.id) as invoices, SUM(invoices.total) as revenue")
            ->where("invoices.status", "paid")
            ->groupBy("clients.id", "clients.first_name", "clients.last_name", "clients.email", "clients.company_name")
            ->orderBy("revenue", "desc")->limit(10)->get();
        return ["columns" => ["ID", "Client", "Email", "Company", "Invoices", "Revenue"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
