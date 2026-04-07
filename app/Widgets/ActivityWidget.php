<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class ActivityWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Recent Activity'; }
    public function getDescription(): string { return 'Latest system events'; }
    public function getColumns(): int { return 2; }
    public function getWeight(): int { return 100; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 60; }

    public function getData(): array
    {
        return DB::table("activity_logs")->select("description", "user", "ip_address", "created_at")->orderBy("created_at", "desc")->limit(8)->get()->toArray();
    }

    public function render(array $data): string
    {
        if (empty($data)) return '<div style="padding:24px;text-align:center;color:var(--pn-muted);">No recent activity</div>';
        $html = "";
        foreach ($data as $a) { $a = (object)$a; $html .= '<div style="padding:8px 14px;border-bottom:1px solid var(--pn-border);font-size:12px;display:flex;justify-content:space-between;gap:8px;"><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'.$a->description.'</span><span style="color:var(--pn-muted);white-space:nowrap;">'.\Carbon\Carbon::parse($a->created_at)->diffForHumans().'</span></div>'; }
        return $html;
    }
}