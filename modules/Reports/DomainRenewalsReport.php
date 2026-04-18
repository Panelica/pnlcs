<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DomainRenewalsReport extends AbstractReport
{
    public function getTitle(): string { return 'Domain Renewal Reminders'; }
    public function getDescription(): string { return 'Domains expiring in the next 90 days'; }
    public function getCategory(): string { return 'Domain'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("domains")
            ->join("clients", "clients.id", "=", "domains.client_id")
            ->selectRaw("domains.id, domains.domain, CONCAT(clients.first_name, ' ', clients.last_name) as client, domains.registrar, domains.expiry_date, DATEDIFF(domains.expiry_date, NOW()) as days_left, domains.recurring_amount")
            ->where("domains.status", "active")
            ->whereRaw("domains.expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 90 DAY)")
            ->orderBy("domains.expiry_date")->get();
        return ["columns" => ["ID", "Domain", "Client", "Registrar", "Expiry", "Days Left", "Amount"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
