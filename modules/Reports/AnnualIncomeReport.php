<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnualIncomeReport extends AbstractReport
{
    public function getTitle(): string { return 'Annual Income Report'; }
    public function getDescription(): string { return 'Yearly income overview with monthly breakdown'; }
    public function getCategory(): string { return 'Financial'; }

    public function generate(Request $request): array
    {
        $year = $this->getYear($request);
        $rows = DB::table("transactions")
            ->whereNotIn("gateway", \App\Models\Transaction::NON_REVENUE_GATEWAYS)
            ->selectRaw("MONTH(date) as month_num, MONTHNAME(date) as month, SUM(amount_in) as income, SUM(fees) as fees, SUM(amount_out) as refunds, (SUM(amount_in) - SUM(fees) - SUM(amount_out)) as net")
            ->whereYear("date", $year)
            ->groupByRaw("MONTH(date), MONTHNAME(date)")->orderByRaw("MONTH(date)")->get();
        $totals = ["", "", $rows->sum("income"), $rows->sum("fees"), $rows->sum("refunds"), $rows->sum("net")];
        return ["columns" => ["#", "Month", "Income", "Fees", "Refunds", "Net"], "rows" => $rows->toArray(), "totals" => $totals];
    }
    public function hasDateFilter(): bool { return false; }
}
