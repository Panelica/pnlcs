<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewCustomersReport extends AbstractReport
{
    public function getTitle(): string { return 'New Customers'; }
    public function getDescription(): string { return 'Client registrations over time'; }
    public function getCategory(): string { return 'Client'; }

    public function generate(Request $request): array
    {
        [$from, $to] = $this->getDateRange($request);
        $rows = DB::table("clients")
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as new_clients")
            ->whereBetween("created_at", [$from, $to." 23:59:59"])
            ->groupBy("month")->orderBy("month", "desc")->get();
        return ["columns" => ["Month", "New Clients"], "rows" => $rows->toArray(), "totals" => ["Total", $rows->sum("new_clients")]];
    }
}
