<?php

namespace Modules\Reports;

use App\Models\Service;
use App\Reports\AbstractReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServerRevenueReport extends AbstractReport
{
    public function getTitle(): string
    {
        return 'Server Revenue Forecast';
    }

    public function getDescription(): string
    {
        return 'Revenue per server from active services';
    }

    public function getCategory(): string
    {
        return 'Service';
    }

    public function generate(Request $request): array
    {
        // A yearly price is not a monthly one. Summing services.amount and
        // calling the total "Monthly Revenue" overstated the forecast by the
        // length of each billing cycle - twelvefold for an annual service.
        $rows = DB::table('services')
            ->leftJoin('servers', 'servers.id', '=', 'services.server_id')
            ->selectRaw("COALESCE(servers.name, 'Unassigned') as server, COUNT(services.id) as services_count, ROUND(SUM(services.amount / ".$this->monthsExpression().'), 2) as monthly_revenue')
            ->where('services.status', 'active')
            ->groupBy('servers.name')->orderBy('monthly_revenue', 'desc')->get();

        return ['columns' => ['Server', 'Services', 'Monthly Revenue'], 'rows' => $rows->toArray(), 'totals' => ['Total', $rows->sum('services_count'), round($rows->sum('monthly_revenue'), 2)]];
    }

    /**
     * Months per billing cycle, straight from the model's map so there is one
     * place to change. The cycle is stored inconsistently ("Semi-Annually",
     * "semiannually"), so it is normalised before matching; anything
     * unrecognised counts as monthly, as it did before.
     */
    private function monthsExpression(): string
    {
        $normalised = "LOWER(REPLACE(REPLACE(REPLACE(services.billing_cycle, ' ', ''), '-', ''), '_', ''))";

        $cases = '';
        foreach (Service::CYCLE_MONTHS as $cycle => $months) {
            $cases .= " WHEN '{$cycle}' THEN {$months}";
        }

        return "(CASE {$normalised}{$cases} ELSE 1 END)";
    }

    public function hasDateFilter(): bool
    {
        return false;
    }
}
