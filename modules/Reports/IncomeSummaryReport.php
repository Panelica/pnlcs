<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncomeSummaryReport extends AbstractReport
{
    public function getTitle(): string { return 'Income Summary'; }
    public function getDescription(): string { return 'Monthly income breakdown with trends'; }
    public function getCategory(): string { return 'Financial'; }

    public function generate(Request $request): array
    {
        [$from, $to] = $this->getDateRange($request);
        $rows = DB::table("transactions")
            ->whereNotIn("gateway", \App\Models\Transaction::NON_REVENUE_GATEWAYS)
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(amount_in) as income, SUM(fees) as fees, SUM(amount_out) as refunds")
            ->whereBetween("date", [$from, $to])
            ->groupBy("month")->orderBy("month", "desc")->get();
        $totals = ["", $rows->sum("income"), $rows->sum("fees"), $rows->sum("refunds")];
        return ["columns" => ["Month", "Income", "Fees", "Refunds"], "rows" => $rows->toArray(), "totals" => $totals];
    }
}
