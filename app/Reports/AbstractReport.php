<?php

namespace App\Reports;

use App\Contracts\ReportModuleInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class AbstractReport implements ReportModuleInterface
{
    public function getSlug(): string
    {
        return Str::slug($this->getTitle());
    }

    public function hasDateFilter(): bool
    {
        return true;
    }

    /**
     * A report that reads a year ('year') or a month ('month') instead of a
     * date range says so here, and the report page offers that choice. Not on
     * ReportModuleInterface, so reports written against it keep working.
     */
    public function periodFilter(): ?string
    {
        return null;
    }

    public function hasCurrencyFilter(): bool
    {
        return false;
    }

    public function canExport(): bool
    {
        return true;
    }

    /**
     * Get date range from request with sensible defaults.
     */
    protected function getDateRange(Request $request): array
    {
        // r142-endofday: these are two dates. A column that carries a time -
        // invoices.date_paid is a timestamp - must be filtered to the end of
        // the closing day, or everything taken after midnight on it falls
        // outside the report. Reports that filter a timestamp append
        // ' 23:59:59' to $to; the ones that filter a date column do not need to.
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return [$from, $to];
    }

    /**
     * Get year from request.
     */
    protected function getYear(Request $request): int
    {
        return (int) $request->input('year', now()->year);
    }

    /**
     * Get month from request.
     */
    protected function getMonth(Request $request): int
    {
        return (int) $request->input('month', now()->month);
    }
}
