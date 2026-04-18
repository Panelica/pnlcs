<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncomeForecastReport extends AbstractReport
{
    public function getTitle(): string { return 'Income Forecast'; }
    public function getDescription(): string { return 'Projected income from upcoming renewals'; }
    public function getCategory(): string { return 'Financial'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("services")
            ->join("products", "products.id", "=", "services.product_id")
            ->selectRaw("DATE_FORMAT(services.next_due_date, '%Y-%m') as month, COUNT(*) as renewals, SUM(services.amount) as projected")
            ->where("services.status", "active")
            ->where("services.next_due_date", ">=", now())
            ->where("services.next_due_date", "<=", now()->addMonths(12))
            ->groupBy("month")->orderBy("month")->get();
        return ["columns" => ["Month", "Renewals Due", "Projected Income"], "rows" => $rows->toArray(), "totals" => ["Total", $rows->sum("renewals"), $rows->sum("projected")]];
    }
    public function hasDateFilter(): bool { return false; }
}
