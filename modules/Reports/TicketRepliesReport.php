<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketRepliesReport extends AbstractReport
{
    public function getTitle(): string { return 'Support Ticket Replies'; }
    public function getDescription(): string { return 'Reply count and response time by staff'; }
    public function getCategory(): string { return 'Support'; }

    public function generate(Request $request): array
    {
        [$from, $to] = $this->getDateRange($request);
        $rows = DB::table("ticket_replies")
            ->leftJoin("admins", "admins.id", "=", "ticket_replies.admin_id")
            ->selectRaw("COALESCE(CONCAT(admins.first_name, ' ', admins.last_name), 'Client') as staff, COUNT(*) as replies")
            ->whereBetween("ticket_replies.created_at", [$from, $to." 23:59:59"])
            ->groupBy("staff")->orderBy("replies", "desc")->get();
        return ["columns" => ["Staff", "Replies"], "rows" => $rows->toArray(), "totals" => ["Total", $rows->sum("replies")]];
    }
}
