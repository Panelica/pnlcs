<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiskUsageReport extends AbstractReport
{
    public function getTitle(): string { return 'Disk Usage Summary'; }
    public function getDescription(): string { return 'Disk usage per service from last usage poll'; }
    public function getCategory(): string { return 'Service'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("services")
            ->join("clients", "clients.id", "=", "services.client_id")
->whereNull("clients.deleted_at")
            ->selectRaw("services.id, CONCAT(clients.first_name, ' ', clients.last_name) as client, services.domain, services.disk_usage as disk_mb, services.disk_limit as limit_mb, CASE WHEN services.disk_limit > 0 THEN ROUND((services.disk_usage / services.disk_limit) * 100, 1) ELSE 0 END as pct")
            ->where("services.status", "active")
            ->where("services.disk_usage", ">", 0)
            ->orderBy("pct", "desc")->limit(100)->get();
        return ["columns" => ["ID", "Client", "Domain", "Used (MB)", "Limit (MB)", "Usage %"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
