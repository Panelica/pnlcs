<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class OrdersWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Orders'; }
    public function getDescription(): string { return 'Recent orders'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 40; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 120; }

    public function getData(): array
    {
        return [
            "pending" => DB::table("orders")->where("status", "pending")->count(),
            "recent" => DB::table("orders")->leftJoin("clients", "clients.id", "=", "orders.client_id")->select("orders.id", "orders.order_number", "orders.status", "orders.total", "orders.created_at", DB::raw("CONCAT(clients.first_name, ' ', clients.last_name) as client"))->orderBy("orders.created_at", "desc")->limit(5)->get(),
        ];
    }

    public function render(array $data): string
    {
        $html = '<div style="padding:12px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;">Pending: <b style="color:#f89406;">'.$data["pending"].'</b></div>';
        foreach ($data["recent"] as $o) { $html .= '<div style="padding:8px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;"><a href="/admin/orders/'.$o->id.'" style="color:var(--pn-link);">#'.$o->order_number.'</a> — '.$o->client.'<span style="float:right;">$'.number_format($o->total,2).'</span></div>'; }
        return $html;
    }
}