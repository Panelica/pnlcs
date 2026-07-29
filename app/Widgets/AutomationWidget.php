<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class AutomationWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Automation'; }
    public function getDescription(): string { return 'Cron job status'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 90; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 300; }

    public function getData(): array
    {
        // Written by RecordCronHeartbeat. This used to search the activity log
        // for the word "cron", which nothing wrote, so a healthy installation
        // was told its automation had never run.
        $lastRun = \App\Models\Setting::get('LastCronRun', '');

        return [
            "active_services" => DB::table("services")->where("status", "active")->count(),
            "overdue_invoices" => DB::table("invoices")->where("status", "overdue")->count(),
            "suspended_services" => DB::table("services")->where("status", "suspended")->count(),
            "last_cron" => $lastRun !== '' ? $lastRun : "Never",
        ];
    }

    public function render(array $data): string
    {
        $items = [
            ["Active Services", $data["active_services"], "#46a546"],
            ["Overdue Invoices", $data["overdue_invoices"], "#c43c35"],
            ["Suspended", $data["suspended_services"], "#f89406"],
            ["Last Cron", $data["last_cron"] === "Never" ? "Never" : \Carbon\Carbon::parse($data["last_cron"])->diffForHumans(), "#337ab7"],
        ];
        $html = "";
        foreach ($items as [$label, $value, $color]) { $html .= '<div style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;"><span>'.$label.'</span><span style="font-weight:600;color:'.$color.';">'.$value.'</span></div>'; }
        return $html;
    }
}