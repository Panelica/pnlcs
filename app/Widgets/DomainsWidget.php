<?php

namespace App\Widgets;

use App\Constants\Permissions;
use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class DomainsWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Domains'; }
    public function getDescription(): string { return 'Upcoming renewals'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 60; }
    public function getPermission(): ?string { return Permissions::LIST_DOMAINS; }
    public function getCacheTtl(): int { return 300; }

    public function getData(): array
    {
        // What can still be renewed, and is due to be. A domain in grace has
        // already expired and is in the window where the registry still renews
        // it at the ordinary price - the invoice generator bills active and
        // grace together for exactly that reason - so it is the most urgent
        // renewal there is. It used to appear nowhere: its status is not
        // active, and its expiry date is in the past, so it failed both halves
        // of the query and the widget titled "Upcoming renewals" showed
        // everything except the domains about to be lost.
        $renewable = fn ($q) => $q
            ->whereIn("status", ["active", "grace"])
            ->whereRaw("expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)")
            ->whereRaw("(status = 'grace' OR expiry_date >= CURDATE())");

        return [
            "total" => DB::table("domains")->count(),
            "expiring" => $renewable(DB::table("domains"))->count(),
            "upcoming" => $renewable(DB::table("domains")->select("domain", "expiry_date"))->orderBy("expiry_date")->limit(5)->get()->map(fn($r) => (array) $r)->toArray(),
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