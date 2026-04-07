<?php

namespace Modules\Reports;

use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionsUsageReport extends AbstractReport
{
    public function getTitle(): string { return 'Promotions Usage'; }
    public function getDescription(): string { return 'Promotion code usage and revenue impact'; }
    public function getCategory(): string { return 'Promotion'; }

    public function generate(Request $request): array
    {
        $rows = DB::table("promotions")
            ->selectRaw("code, type, value, uses, max_uses, CASE WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 'Expired' WHEN max_uses > 0 AND uses >= max_uses THEN 'Maxed' ELSE 'Active' END as status, recurring, starts_at, expires_at")
            ->orderBy("uses", "desc")->get();
        return ["columns" => ["Code", "Type", "Value", "Uses", "Max", "Status", "Recurring", "Start", "Expiry"], "rows" => $rows->toArray()];
    }
    public function hasDateFilter(): bool { return false; }
}
