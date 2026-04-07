<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketRatingsReport extends AbstractReport
{
    public function getTitle(): string { return 'Ticket Ratings by Staff'; }
    public function getDescription(): string { return 'Individual staff performance ratings'; }
    public function getCategory(): string { return 'Support'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("ticket_feedback")
            ->join("tickets", "tickets.id", "=", "ticket_feedback.ticket_id")
            ->leftJoin("ticket_replies", function($j) { $j->on("ticket_replies.ticket_id", "=", "tickets.id")->whereNotNull("ticket_replies.admin"); })
            ->selectRaw("COALESCE(ticket_replies.admin, 'Unassigned') as staff, COUNT(*) as reviews, ROUND(AVG(ticket_feedback.rating), 1) as avg_rating, MIN(ticket_feedback.rating) as min_rating, MAX(ticket_feedback.rating) as max_rating")
            ->groupBy("staff")->orderBy("avg_rating", "desc")->get();
        return ["columns" => ["Staff", "Reviews", "Avg", "Min", "Max"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
