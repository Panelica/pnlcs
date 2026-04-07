<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketFeedbackReport extends AbstractReport
{
    public function getTitle(): string { return 'Ticket Feedback Scores'; }
    public function getDescription(): string { return 'Customer satisfaction ratings for resolved tickets'; }
    public function getCategory(): string { return 'Support'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("ticket_feedback")
            ->join("tickets", "tickets.id", "=", "ticket_feedback.ticket_id")
            ->leftJoin("ticket_departments", "ticket_departments.id", "=", "tickets.department_id")
            ->selectRaw("COALESCE(ticket_departments.name, 'N/A') as department, COUNT(*) as reviews, ROUND(AVG(ticket_feedback.rating), 1) as avg_rating")
            ->groupBy("department")->orderBy("avg_rating", "desc")->get();
        return ["columns" => ["Department", "Reviews", "Avg Rating"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
