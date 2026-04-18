<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketTagsReport extends AbstractReport
{
    public function getTitle(): string { return 'Ticket Tags'; }
    public function getDescription(): string { return 'Most common ticket tags'; }
    public function getCategory(): string { return 'Support'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("ticket_tags")
            ->selectRaw("tag, COUNT(*) as count")
            ->groupBy("tag")->orderBy("count", "desc")->limit(30)->get();
        return ["columns" => ["Tag", "Count"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
