<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DomainsOverviewReport extends AbstractReport
{
    public function getTitle(): string { return 'Domains Overview'; }
    public function getDescription(): string { return 'Domain registration statistics by status'; }
    public function getCategory(): string { return 'Domain'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("domains")
            ->selectRaw("status, registrar, COUNT(*) as count, SUM(recurring_amount) as revenue")
            ->groupBy("status", "registrar")->orderBy("count", "desc")->get();
        return ["columns" => ["Status", "Registrar", "Count", "Recurring Revenue"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
