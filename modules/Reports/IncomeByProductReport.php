<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use App\Services\MassPaymentService;
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
            // rel_id is a service id only on hosting and overage lines; domain,
            // addon and upgrade lines carry their own row's id, which matched
            // an unrelated service and credited its product.
            ->leftJoin("services", fn ($join) => $join->on("services.id", "=", "invoice_items.rel_id")
                ->whereIn("invoice_items.type", ["Hosting", "Overage"]))
            ->leftJoin("products", "products.id", "=", "services.product_id")
            // A line's value is its unit price times its quantity, as on the
            // invoice itself - which never charges for less than one, however
            // the row was written: Invoice::lineTax() and
            // ConfigOptionService::priceOf() both read max(1, qty). A qty of 0
            // reaches invoice_items through a cart config option and through
            // the WHMCS import, and multiplying by it would report as nothing
            // a line the customer was charged for.
            ->selectRaw("COALESCE(products.name, invoice_items.description) as product, COUNT(DISTINCT invoices.id) as invoices, SUM(invoice_items.amount * GREATEST(COALESCE(invoice_items.qty, 1), 1)) as revenue")
            // A top-up and a payment for several invoices sell nothing: the
            // money they bring in pays other invoices, and those invoices'
            // lines are the revenue. Counting both reported it twice.
            ->whereNotIn("invoice_items.type", ["AddFunds", MassPaymentService::ITEM_TYPE])
            ->where("invoices.status", "paid")
            ->where("invoices.type", "!=", "proforma")
            ->whereBetween("invoices.date_paid", [$from, $to.' 23:59:59'])
            ->groupBy("product")->orderBy("revenue", "desc")->get();
        return ["columns" => ["Product", "Invoices", "Revenue"], "rows" => $rows->toArray(), "totals" => ["Total", $rows->sum("invoices"), $rows->sum("revenue")]];
    }
}
