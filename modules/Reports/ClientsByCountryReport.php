<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientsByCountryReport extends AbstractReport
{
    public function getTitle(): string { return 'Clients by Country'; }
    public function getDescription(): string { return 'Geographic distribution of clients'; }
    public function getCategory(): string { return 'Client'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("clients")
            ->selectRaw("COALESCE(NULLIF(TRIM(country), ''), 'Unknown') as country, COUNT(*) as clients, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active")
            ->groupBy("country")->orderBy("clients", "desc")->get();
        return ["columns" => ["Country", "Total Clients", "Active"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
