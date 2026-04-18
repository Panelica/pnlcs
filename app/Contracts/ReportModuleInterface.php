<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface ReportModuleInterface
{
    public function getTitle(): string;
    public function getDescription(): string;
    public function getCategory(): string;
    public function getSlug(): string;

    /**
     * Generate report data.
     * @return array{columns: array, rows: array, totals?: array, chart?: array}
     */
    public function generate(Request $request): array;

    /**
     * Whether this report supports date range filtering.
     */
    public function hasDateFilter(): bool;

    /**
     * Whether this report supports currency selection.
     */
    public function hasCurrencyFilter(): bool;

    /**
     * Whether this report supports CSV export.
     */
    public function canExport(): bool;
}
