<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServerRevenueReport extends AbstractReport
{
    public function getTitle(): string { return 'Server Revenue Forecast'; }
    public function getDescription(): string { return 'Revenue per server from active services'; }
    public function getCategory(): string { return 'Service'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("services")
            ->leftJoin("servers", "servers.id", "=", "services.server_id")
            ->selectRaw("COALESCE(servers.name, 'Unassigned') as server, COUNT(services.id) as services_count, SUM(services.amount) as monthly_revenue")
            ->where("services.status", "active")
            ->groupBy("servers.name")->orderBy("monthly_revenue", "desc")->get();
        return ["columns" => ["Server", "Services", "Monthly Revenue"], "rows" => $rows->toArray(), "totals" => ["Total", $rows->sum("services_count"), $rows->sum("monthly_revenue")]];
    }
    public function hasDateFilter(): bool { return false; }
}
