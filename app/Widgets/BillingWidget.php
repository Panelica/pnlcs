<?php

namespace App\Widgets;

use App\Constants\Permissions;
use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class BillingWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'Billing'; }
    public function getDescription(): string { return 'Income overview'; }
    public function getColumns(): int { return 2; }
    public function getWeight(): int { return 10; }
    public function getPermission(): ?string { return Permissions::VIEW_REPORTS; }
    public function getCacheTtl(): int { return 120; }

    public function getData(): array
    {
        $today = \Carbon\Carbon::today();

        // Net of refunds, which is what every report in the product means by
        // income: the annual report nets income against fees and refunds, the
        // income summary breaks the three out, and the top clients report calls
        // SUM(amount_in - amount_out) revenue. Summing only amount_in left a
        // refunded payment counted as income for ever, so the front page and
        // the reports disagreed about the same month.
        //
        // Affiliate commission and payouts share this table and are not money
        // the business took in; the revenue scope already leaves those out.
        $net = \Illuminate\Support\Facades\DB::raw("amount_in - amount_out");

        return [
            "today" => \App\Models\Transaction::revenue()->whereDate("date", $today)->sum($net),
            "month" => \App\Models\Transaction::revenue()->where("date", ">=", $today->startOfMonth())->sum($net),
            "year" => \App\Models\Transaction::revenue()->where("date", ">=", $today->startOfYear())->sum($net),
            "all" => \App\Models\Transaction::revenue()->sum($net),
        ];
    }

    public function render(array $data): string
    {
        return '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
            <div style="text-align:center;padding:16px;border-right:1px solid var(--pn-border);border-bottom:1px solid var(--pn-border);">
                <div style="font-size:22px;font-weight:700;color:#46a546;">$'.number_format($data["today"],2).'</div><div style="font-size:11px;color:var(--pn-muted);">Today</div></div>
            <div style="text-align:center;padding:16px;border-bottom:1px solid var(--pn-border);">
                <div style="font-size:22px;font-weight:700;color:#f89406;">$'.number_format($data["month"],2).'</div><div style="font-size:11px;color:var(--pn-muted);">This Month</div></div>
            <div style="text-align:center;padding:16px;border-right:1px solid var(--pn-border);">
                <div style="font-size:22px;font-weight:700;color:#c43c35;">$'.number_format($data["year"],2).'</div><div style="font-size:11px;color:var(--pn-muted);">This Year</div></div>
            <div style="text-align:center;padding:16px;">
                <div style="font-size:22px;font-weight:700;">$'.number_format($data["all"],2).'</div><div style="font-size:11px;color:var(--pn-muted);">All Time</div></div>
        </div>';
    }
}