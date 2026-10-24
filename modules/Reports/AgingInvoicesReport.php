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
            // The statuses Invoice::scopeOutstanding() counts: a part-paid
            // invoice was left out, although money is still owed on it.
            ->whereIn("invoices.status", ["unpaid", "overdue", "partially_paid"])
            ->orderBy("days_overdue", "desc")->get();

        // What is still owed beside the total: a part-paid invoice's total
        // overstated the debt.
        $payments = app(\App\Services\PaymentService::class);
        $invoices = \App\Models\Invoice::whereIn("id", $rows->pluck("id"))->get()->keyBy("id");
        $rows = $rows->map(fn ($row) => (object) [
            "id" => $row->id, "client" => $row->client, "total" => $row->total,
            "balance" => isset($invoices[$row->id]) ? $payments->balance($invoices[$row->id]) : $row->total,
            "due_date" => $row->due_date, "days_overdue" => $row->days_overdue, "aging_bracket" => $row->aging_bracket,
        ]);

        return ["columns" => ["Invoice #", "Client", "Total", "Balance", "Due Date", "Days Overdue", "Bracket"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
