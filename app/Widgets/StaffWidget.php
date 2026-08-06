<?php

namespace App\Widgets;

use App\Constants\Permissions;
use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class StaffWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Staff Online'; }
    public function getDescription(): string { return 'Admin login status'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 110; }
    public function getPermission(): ?string { return Permissions::MANAGE_STAFF; }
    public function getCacheTtl(): int { return 120; }

    public function getData(): array
    {
        return DB::table("admins")->select("id", "first_name", "last_name", "email", "last_login")->orderByDesc("last_login")->limit(5)->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function render(array $data): string
    {
        $html = "";
        foreach ($data as $a) { $ago = ($a["last_login"] ?? null) ? \Carbon\Carbon::parse($a["last_login"])->diffForHumans() : "Never"; $html .= '<div style="padding:8px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;">'.e($a["first_name"]).' '.e($a["last_name"]).'<span style="float:right;font-size:11px;color:var(--pn-muted);">'.e($ago).'</span></div>'; }
        return $html;
    }
}