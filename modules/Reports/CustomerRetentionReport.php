<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerRetentionReport extends AbstractReport
{
    public function getTitle(): string { return 'Customer Retention'; }
    public function getDescription(): string { return 'Average customer lifetime and churn'; }
    public function getCategory(): string { return 'Client'; }

    public function generate(Request $request): array
    {
        $active = DB::table("clients")->where("status", "active")->count();
        $inactive = DB::table("clients")->where("status", "!=", "active")->count();
        $total = $active + $inactive;
        $avgAge = DB::table("clients")->where("status", "active")->selectRaw("AVG(DATEDIFF(NOW(), created_at)) as avg_days")->value("avg_days");
        $rows = [
            (object)["metric" => "Active Clients", "value" => $active],
            (object)["metric" => "Inactive/Closed", "value" => $inactive],
            (object)["metric" => "Retention Rate", "value" => $total > 0 ? round(($active / $total) * 100, 1) . "%" : "N/A"],
            (object)["metric" => "Avg Client Age (days)", "value" => round($avgAge ?? 0)],
        ];
        return ["columns" => ["Metric", "Value"], "rows" => $rows];
    }
    public function hasDateFilter(): bool { return false; }
}
