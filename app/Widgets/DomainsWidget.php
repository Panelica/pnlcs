<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class DomainsWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Domains'; }
    public function getDescription(): string { return 'Upcoming renewals'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 60; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 300; }

    public function getData(): array
    {
        return [
            "total" => DB::table("domains")->count(),
            "expiring" => DB::table("domains")->where("status", "active")->whereRaw("expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)")->count(),
            "upcoming" => DB::table("domains")->select("domain", "expiry_date")->where("status", "active")->whereRaw("expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)")->orderBy("expiry_date")->limit(5)->get()->map(fn($r) => (array) $r)->toArray(),
        ];
    }

    public function render(array $data): string
    {
        $html = '<div style="padding:12px 16px;border-bottom:1px solid var(--pn-border);display:flex;justify-content:space-between;font-size:13px;"><span>Total: <b>'.e($data["total"]).'</b></span><span style="color:#c43c35;">Expiring (30d): <b>'.e($data["expiring"]).'</b></span></div>';
        foreach ($data["upcoming"] as $d) { $html .= '<div style="padding:8px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;">'.e($d["domain"]).'<span style="float:right;color:var(--pn-muted);font-size:11px;">'.e($d["expiry_date"]).'</span></div>'; }
        if (empty($data["upcoming"])) { $html .= '<div style="padding:16px;text-align:center;font-size:13px;color:var(--pn-muted);">No domains expiring soon</div>'; }
        return $html;
    }
}