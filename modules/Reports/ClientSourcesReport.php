<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientSourcesReport extends AbstractReport
{
    public function getTitle(): string { return 'Client Sources'; }
    public function getDescription(): string { return 'How clients found your service'; }
    public function getCategory(): string { return 'Client'; }

    public function generate(Request $request): array
    {
        // The source PNLCS records is the referring affiliate. This grouped by
        // clients.notes, staff's private notes, so every note became a
        // "source" and its text was printed in the report.
        $rows = DB::table("clients")->whereNull("deleted_at")
            ->selectRaw("CASE WHEN affiliate_id IS NULL THEN 'Direct/Unknown' ELSE 'Affiliate referral' END as source, COUNT(*) as clients")
            ->groupBy("source")->orderBy("clients", "desc")->get();
        return ["columns" => ["Source", "Clients"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
