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
            // A ticket the customer has answered is waiting on staff exactly as
            // much as an open one, which is why the Support widget counts both
            // under "Awaiting Reply". This tile counted only Open, so the front
            // page showed a smaller queue than the ticket screen it links to.
            "tickets_open" => DB::table("tickets")->whereIn("status", ["open", "customer-reply"])->count(),
            // An unpaid invoice becomes overdue the day after it is due. Leaving
            // those out meant the longer a customer failed to pay, the less the
            // front page said was owed. Unpaid and overdue together is what the
            // rest of the panel means by still outstanding.
            "invoices_unpaid" => DB::table("invoices")->whereIn("status", ["unpaid", "overdue"])->count(),
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
            $html .= '<a href="/'.e($link).'" style="text-decoration:none;color:inherit;text-align:center;padding:16px 8px;border-right:1px solid var(--pn-border);">
                <div style="font-size:28px;font-weight:700;color:'.e($color).';">'.e($value).'</div>
                <div style="font-size:11px;color:var(--pn-muted);margin-top:4px;">'.e($label).'</div></a>';
        }
        return $html . '</div>';
    }
}