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
        $rows = DB::table("clients")
            ->selectRaw("COALESCE(NULLIF(TRIM(notes), ''), 'Direct/Unknown') as source, COUNT(*) as clients")
            ->groupBy("source")->orderBy("clients", "desc")->limit(20)->get();
        return ["columns" => ["Source", "Clients"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
