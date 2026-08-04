<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductSuspensionsReport extends AbstractReport
{
    public function getTitle(): string { return 'Product Suspensions'; }
    public function getDescription(): string { return 'Currently suspended services'; }
    public function getCategory(): string { return 'Service'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("services")
            ->join("clients", "clients.id", "=", "services.client_id")
->whereNull("clients.deleted_at")
            ->leftJoin("products", "products.id", "=", "services.product_id")
            ->selectRaw("services.id, CONCAT(clients.first_name, ' ', clients.last_name) as client, COALESCE(products.name, 'N/A') as product, services.domain, services.suspension_reason, services.updated_at as suspended_at")
            ->where("services.status", "suspended")
            ->orderBy("services.updated_at", "desc")->get();
        return ["columns" => ["ID", "Client", "Product", "Domain", "Reason", "Suspended At"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
