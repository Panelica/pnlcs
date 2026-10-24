<?php

use App\Models\Admin;
use App\Models\AdminRole;
use Modules\Reports\AnnualIncomeReport;
use Modules\Reports\DailyPerformanceReport;

/**
 * Annual Income reads a year and Daily Performance a month, and both turn the
 * date range off. The report page drew only the date range, so these two had
 * no field at all: the period could be changed through the address alone.
 */
function periodReportAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

it('offers a year for Annual Income', function () {
    $html = $this->actingAs(periodReportAdmin(), 'admin')
        ->get(route('admin.reports.show', (new AnnualIncomeReport)->getSlug()).'?year=2024')->assertOk()->getContent();

    expect($html)->toMatch('#<input type="number" id="report-year" name="year" value="2024"#')
        ->and($html)->not->toContain('name="month"')
        ->and($html)->not->toContain('name="from"');
});

it('offers a year and a month for Daily Performance', function () {
    $html = $this->actingAs(periodReportAdmin(), 'admin')
        ->get(route('admin.reports.show', (new DailyPerformanceReport)->getSlug()).'?year=2025&month=3')->assertOk()->getContent();

    expect($html)->toContain('name="year" value="2025"')
        ->and($html)->toMatch('#<option value="3"\s+selected#');
});

it('leaves a date-range report as it was', function () {
    $html = $this->actingAs(periodReportAdmin(), 'admin')
        ->get(route('admin.reports.show', (new \Modules\Reports\IncomeByProductReport)->getSlug()))->assertOk()->getContent();

    expect($html)->toContain('name="from"')->and($html)->not->toContain('id="report-year"');
});
