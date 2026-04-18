<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class ServicesWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Services'; }
    public function getDescription(): string { return 'Service status'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 50; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 120; }

    public function getData(): array
    {
        return DB::table("services")->selectRaw("status, COUNT(*) as cnt")->groupBy("status")->pluck("cnt", "status")->toArray();
    }

    public function render(array $data): string
    {
        $colors = ["active" => "#46a546", "suspended" => "#f89406", "terminated" => "#c43c35", "pending" => "#337ab7", "cancelled" => "#999"];
        $html = "";
        foreach ($data as $status => $count) { $color = $colors[$status] ?? "#666"; $html .= '<div style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;"><span style="text-transform:capitalize;">'.$status.'</span><span style="font-weight:700;color:'.$color.';">'.$count.'</span></div>'; }
        return $html;
    }
}