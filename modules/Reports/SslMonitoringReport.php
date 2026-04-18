<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SslMonitoringReport extends AbstractReport
{
    public function getTitle(): string { return 'SSL Certificate Monitoring'; }
    public function getDescription(): string { return 'SSL certificate status and expiry tracking'; }
    public function getCategory(): string { return 'Domain'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("ssl_orders")
            ->join("clients", "clients.id", "=", "ssl_orders.client_id")
            ->selectRaw("ssl_orders.id, CONCAT(clients.first_name, ' ', clients.last_name) as client, ssl_orders.domain, ssl_orders.status, ssl_orders.crt_expires, CASE WHEN ssl_orders.crt_expires IS NOT NULL THEN DATEDIFF(ssl_orders.crt_expires, NOW()) ELSE NULL END as days_left")
            ->orderByRaw("CASE WHEN ssl_orders.crt_expires IS NULL THEN 1 ELSE 0 END, ssl_orders.crt_expires")->get();
        return ["columns" => ["ID", "Client", "Domain", "Status", "Expires", "Days Left"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
