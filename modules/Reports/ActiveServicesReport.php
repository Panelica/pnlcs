<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActiveServicesReport extends AbstractReport
{
    public function getTitle(): string { return 'Active Services'; }
    public function getDescription(): string { return 'All active services with product details'; }
    public function getCategory(): string { return 'Service'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("services")
            ->join("clients", "clients.id", "=", "services.client_id")
            ->leftJoin("products", "products.id", "=", "services.product_id")
            ->selectRaw("services.id, CONCAT(clients.first_name, ' ', clients.last_name) as client, COALESCE(products.name, 'N/A') as product, services.domain, services.amount, services.billing_cycle, services.next_due_date, services.status")
            ->where("services.status", "active")
            ->orderBy("services.next_due_date")->limit(500)->get();
        return ["columns" => ["ID", "Client", "Product", "Domain", "Amount", "Cycle", "Next Due", "Status"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
