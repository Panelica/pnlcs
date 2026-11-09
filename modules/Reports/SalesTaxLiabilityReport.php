<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use App\Services\MassPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesTaxLiabilityReport extends AbstractReport
{
    public function getTitle(): string { return 'Sales Tax Liability'; }
    public function getDescription(): string { return 'Tax collected on paid invoices'; }
    public function getCategory(): string { return 'Financial'; }

    public function generate(Request $request): array
    {
        [$from, $to] = $this->getDateRange($request);
        $rows = DB::table("invoices")
            ->selectRaw("DATE_FORMAT(date_paid, '%Y-%m') as month, SUM(subtotal) as subtotal, SUM(tax) as tax, SUM(tax2) as tax2, SUM(total) as total")
            ->where("status", "paid")->where("type", "!=", "proforma")->whereBetween("date_paid", [$from, $to.' 23:59:59'])
            // A top-up and a payment for several invoices are no sale: the
            // invoices their money pays are counted here on their own.
            ->where("type", "!=", MassPaymentService::TYPE)
            ->whereNotExists(fn ($q) => $q->selectRaw("1")->from("invoice_items")
                ->whereColumn("invoice_items.invoice_id", "invoices.id")
                ->whereIn("invoice_items.type", ["AddFunds", MassPaymentService::ITEM_TYPE]))
            ->groupBy("month")->orderBy("month", "desc")->get();
        return ["columns" => ["Month", "Subtotal", "Tax", "Tax 2", "Total"], "rows" => $rows->toArray(), "totals" => ["Total", $rows->sum("subtotal"), $rows->sum("tax"), $rows->sum("tax2"), $rows->sum("total")]];
    }
}
