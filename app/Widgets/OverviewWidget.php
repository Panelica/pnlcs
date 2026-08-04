<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class OverviewWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Overview'; }
    public function getDescription(): string { return 'Quick stats'; }
    public function getColumns(): int { return 4; }
    public function getWeight(): int { return 5; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 120; }

    public function getData(): array
    {
        return [
            "clients" => DB::table("clients")->whereNull("deleted_at")->count(),
            "services" => DB::table("services")->where("status", "active")->count(),
            "domains" => DB::table("domains")->where("status", "active")->count(),
            "orders_pending" => DB::table("orders")->where("status", "pending")->count(),
            "tickets_open" => DB::table("tickets")->where("status", "open")->count(),
            "invoices_unpaid" => DB::table("invoices")->where("status", "unpaid")->count(),
        ];
    }

    public function render(array $data): string
    {
        $stats = [
            ["Clients", $data["clients"], "#337ab7", "admin/clients"],
            ["Active Services", $data["services"], "#46a546", "admin/services"],
            ["Active Domains", $data["domains"], "#008b8b", "admin/domains"],
            ["Pending Orders", $data["orders_pending"], "#f89406", "admin/orders"],
            ["Open Tickets", $data["tickets_open"], "#c43c35", "admin/tickets"],
            ["Unpaid Invoices", $data["invoices_unpaid"], "#d68100", "admin/invoices"],
        ];
        $html = '<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:0;">';
        foreach ($stats as [$label, $value, $color, $link]) {
            $html .= '<a href="/'.$link.'" style="text-decoration:none;color:inherit;text-align:center;padding:16px 8px;border-right:1px solid var(--pn-border);">
                <div style="font-size:28px;font-weight:700;color:'.$color.';">'.$value.'</div>
                <div style="font-size:11px;color:var(--pn-muted);margin-top:4px;">'.$label.'</div></a>';
        }
        return $html . '</div>';
    }
}