<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgingInvoicesReport extends AbstractReport
{
    public function getTitle(): string { return 'Aging Invoices'; }
    public function getDescription(): string { return 'Unpaid invoices grouped by age'; }
    public function getCategory(): string { return 'Financial'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("invoices")
            ->join("clients", "clients.id", "=", "invoices.client_id")
->whereNull("clients.deleted_at")
            ->selectRaw("invoices.id, CONCAT(clients.first_name, ' ', clients.last_name) as client, invoices.total, invoices.due_date, DATEDIFF(NOW(), invoices.due_date) as days_overdue, CASE WHEN DATEDIFF(NOW(), invoices.due_date) <= 30 THEN '0-30 days' WHEN DATEDIFF(NOW(), invoices.due_date) <= 60 THEN '31-60 days' WHEN DATEDIFF(NOW(), invoices.due_date) <= 90 THEN '61-90 days' ELSE '90+ days' END as aging_bracket")
            ->whereIn("invoices.status", ["unpaid", "overdue"])
            ->orderBy("days_overdue", "desc")->get();
        return ["columns" => ["Invoice #", "Client", "Total", "Due Date", "Days Overdue", "Bracket"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
