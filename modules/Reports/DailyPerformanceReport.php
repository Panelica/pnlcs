<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyPerformanceReport extends AbstractReport
{
    public function getTitle(): string { return 'Daily Performance'; }
    public function getDescription(): string { return 'Daily activity summary for a given month'; }
    public function getCategory(): string { return 'Financial'; }

    public function generate(Request $request): array
    {
        $year = $this->getYear($request);
        $month = $this->getMonth($request);
        $start = sprintf("%04d-%02d-01", $year, $month);
        $end = date("Y-m-t", strtotime($start));
        $orders = DB::table("orders")->selectRaw("DAY(date) as day, COUNT(*) as cnt")->where("status", "active")->whereBetween("date", [$start, $end])->groupByRaw("DAY(date)")->pluck("cnt", "day");
        $invoicesNew = DB::table("invoices")->selectRaw("DAY(date) as day, COUNT(*) as cnt")->whereBetween("date", [$start, $end])->groupByRaw("DAY(date)")->pluck("cnt", "day");
        $invoicesPaid = DB::table("invoices")->selectRaw("DAY(date_paid) as day, COUNT(*) as cnt")->where("status", "paid")->whereBetween("date_paid", [$start, $end])->groupByRaw("DAY(date_paid)")->pluck("cnt", "day");
        $tickets = DB::table("tickets")->selectRaw("DAY(created_at) as day, COUNT(*) as cnt")->whereBetween("created_at", [$start, $end." 23:59:59"])->groupByRaw("DAY(created_at)")->pluck("cnt", "day");
        $rows = [];
        for ($d = 1; $d <= (int)date("d", strtotime($end)); $d++) {
            $rows[] = (object)["day" => $d, "orders" => $orders[$d] ?? 0, "new_invoices" => $invoicesNew[$d] ?? 0, "paid_invoices" => $invoicesPaid[$d] ?? 0, "tickets" => $tickets[$d] ?? 0];
        }
        return ["columns" => ["Day", "Orders", "New Invoices", "Paid Invoices", "Tickets"], "rows" => $rows];
    }
    public function hasDateFilter(): bool { return false; }
}
