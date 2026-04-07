<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class ClientsWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Clients'; }
    public function getDescription(): string { return 'Recent clients'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 30; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 120; }

    public function getData(): array
    {
        return [
            "total" => DB::table("clients")->count(),
            "active" => DB::table("clients")->where("status", "active")->count(),
            "recent" => DB::table("clients")->select("id", "first_name", "last_name", "email", "created_at")->orderBy("created_at", "desc")->limit(5)->get(),
        ];
    }

    public function render(array $data): string
    {
        $html = '<div style="padding:12px 16px;border-bottom:1px solid var(--pn-border);display:flex;justify-content:space-between;"><span style="font-size:13px;">Total: <b>'.$data["total"].'</b></span><span style="font-size:13px;color:#46a546;">Active: <b>'.$data["active"].'</b></span></div>';
        foreach ($data["recent"] as $c) { $html .= '<div style="padding:8px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;"><a href="/admin/clients/'.$c->id.'" style="color:var(--pn-link);">'.$c->first_name.' '.$c->last_name.'</a><div style="font-size:11px;color:var(--pn-muted);">'.$c->email.'</div></div>'; }
        return $html;
    }
}