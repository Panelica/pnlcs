<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncomeByProductReport extends AbstractReport
{
    public function getTitle(): string { return 'Income by Product'; }
    public function getDescription(): string { return 'Revenue breakdown per product/service'; }
    public function getCategory(): string { return 'Financial'; }

    public function generate(Request $request): array
    {
        [$from, $to] = $this->getDateRange($request);
        $rows = DB::table("invoice_items")
            ->join("invoices", "invoices.id", "=", "invoice_items.invoice_id")
            ->leftJoin("services", "services.id", "=", "invoice_items.rel_id")
            ->leftJoin("products", "products.id", "=", "services.product_id")
            ->selectRaw("COALESCE(products.name, invoice_items.description) as product, COUNT(DISTINCT invoices.id) as invoices, SUM(invoice_items.amount) as revenue")
            ->where("invoices.status", "paid")
            ->where("invoices.type", "!=", "proforma")
            ->whereBetween("invoices.date_paid", [$from, $to.' 23:59:59'])
            ->groupBy("product")->orderBy("revenue", "desc")->get();
        return ["columns" => ["Product", "Invoices", "Revenue"], "rows" => $rows->toArray(), "totals" => ["Total", $rows->sum("invoices"), $rows->sum("revenue")]];
    }
}
