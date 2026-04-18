<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AffiliatesOverviewReport extends AbstractReport
{
    public function getTitle(): string { return 'Affiliates Overview'; }
    public function getDescription(): string { return 'Affiliate performance summary'; }
    public function getCategory(): string { return 'Affiliate'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("affiliates")
            ->join("clients", "clients.id", "=", "affiliates.client_id")
            ->selectRaw("affiliates.id, CONCAT(clients.first_name, ' ', clients.last_name) as affiliate, affiliates.visitors, affiliates.balance, affiliates.withdrawn, (affiliates.balance + affiliates.withdrawn) as total_earned")
            ->orderBy("total_earned", "desc")->get();
        return ["columns" => ["ID", "Affiliate", "Visitors", "Balance", "Withdrawn", "Total Earned"], "rows" => $rows->toArray(), "totals" => ["Total", "", $rows->sum("visitors"), $rows->sum("balance"), $rows->sum("withdrawn"), $rows->sum("total_earned")]];
    }
    public function hasDateFilter(): bool { return false; }
}
