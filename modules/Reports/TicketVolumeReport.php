<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketVolumeReport extends AbstractReport
{
    public function getTitle(): string { return 'Support Ticket Volume'; }
    public function getDescription(): string { return 'Ticket volume over time with status breakdown'; }
    public function getCategory(): string { return 'Support'; }

    public function generate(Request $request): array
    {
        [$from, $to] = $this->getDateRange($request);
        $rows = DB::table("tickets")
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total, SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) as open_count, SUM(CASE WHEN status='answered' THEN 1 ELSE 0 END) as answered, SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) as closed")
            ->whereBetween("created_at", [$from, $to." 23:59:59"])
            ->groupBy("month")->orderBy("month", "desc")->get();
        return ["columns" => ["Month", "Total", "Open", "Answered", "Closed"], "rows" => $rows->toArray()];
    }
}
