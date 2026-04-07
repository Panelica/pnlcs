<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class SupportWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Support'; }
    public function getDescription(): string { return 'Ticket overview'; }
    public function getColumns(): int { return 2; }
    public function getWeight(): int { return 20; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 60; }

    public function getData(): array
    {
        return [
            "awaiting" => DB::table("tickets")->whereIn("status", ["open", "customer-reply"])->count(),
            "assigned" => DB::table("tickets")->where("flag", ">", 0)->where("status", "!=", "closed")->count(),
            "recent" => DB::table("tickets")->leftJoin("ticket_departments", "ticket_departments.id", "=", "tickets.department_id")->select("tickets.id", "tickets.title", "tickets.status", "tickets.updated_at", "ticket_departments.name as dept")->orderBy("tickets.updated_at", "desc")->limit(5)->get(),
        ];
    }

    public function render(array $data): string
    {
        $html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid var(--pn-border);">
            <div style="text-align:center;padding:14px;border-right:1px solid var(--pn-border);"><div style="font-size:24px;font-weight:700;color:#337ab7;">'.$data["awaiting"].'</div><div style="font-size:11px;color:var(--pn-muted);">Awaiting Reply</div></div>
            <div style="text-align:center;padding:14px;"><div style="font-size:24px;font-weight:700;color:#c43c35;">'.$data["assigned"].'</div><div style="font-size:11px;color:var(--pn-muted);">Assigned</div></div>
        </div>';
        foreach ($data["recent"] as $t) { $html .= '<div style="padding:8px 14px;border-bottom:1px solid var(--pn-border);font-size:13px;"><a href="/admin/tickets/'.$t->id.'" style="color:var(--pn-link);">#'.$t->id.' '.$t->title.'</a><span style="float:right;font-size:11px;color:var(--pn-muted);">'.\Carbon\Carbon::parse($t->updated_at)->diffForHumans().'</span></div>'; }
        return $html;
    }
}