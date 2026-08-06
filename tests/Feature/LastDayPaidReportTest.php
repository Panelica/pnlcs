<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Modules\Reports\DailyPerformanceReport;
use Modules\Reports\IncomeByProductReport;
use Modules\Reports\SalesTaxLiabilityReport;

/**
 * Money taken on the last day of the range, reported as if it never arrived.
 *
 * invoices.date_paid is a timestamp - twenty-seven invoices on this
 * installation carry a time on it - while the range the operator picks is two
 * dates. Filtering between them compares against midnight on the closing day,
 * so everything paid after 00:00:00 that day falls outside the report.
 *
 * Run "income by product" for a month and the last day is missing. Run the
 * sales tax report and the tax owed for that day is missing with it. Four
 * other reports already append the end of the day when they filter a
 * timestamp - and DailyPerformance does it on the line below the one that
 * counts paid invoices.
 */
function paidInvoiceAt(string $paidAt, float $amount = 120.0, float $tax = 20.0): Invoice
{
    $client = Client::factory()->create();

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'paid',
        'subtotal' => $amount,
        'tax' => $tax,
        'total' => $amount + $tax,
        'date' => substr($paidAt, 0, 10),
        'date_paid' => $paidAt,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'rel_id' => 0,
        'description' => 'Hosting for the last day',
        'amount' => $amount,
        'taxed' => true,
    ]);

    return $invoice;
}

function reportRows(object $report, string $from, string $to): array
{
    return $report->generate(Request::create('/', 'GET', ['from' => $from, 'to' => $to]))['rows'] ?? [];
}

it('counts an invoice paid during the last day of the range', function () {
    paidInvoiceAt('2026-08-31 13:51:37');

    $rows = reportRows(new IncomeByProductReport, '2026-08-01', '2026-08-31');

    expect(collect($rows)->sum('revenue'))->toEqual(120.0);
});

it('counts its tax as well', function () {
    paidInvoiceAt('2026-08-31 13:51:37');

    $rows = reportRows(new SalesTaxLiabilityReport, '2026-08-01', '2026-08-31');

    expect(collect($rows)->sum('tax'))->toEqual(20.0);
});

it('counts it on the day it was paid', function () {
    paidInvoiceAt('2026-08-31 13:51:37');

    $report = new DailyPerformanceReport;
    $rows = $report->generate(Request::create('/', 'GET', ['year' => 2026, 'month' => 8]))['rows'] ?? [];

    $lastDay = collect($rows)->firstWhere('day', 31);

    expect($lastDay)->not->toBeNull();
    expect((int) ($lastDay->paid_invoices ?? 0))->toBe(1);
});

it('still leaves out what was paid after the range', function () {
    paidInvoiceAt('2026-09-01 09:00:00');

    $rows = reportRows(new IncomeByProductReport, '2026-08-01', '2026-08-31');

    expect(collect($rows)->sum('revenue'))->toEqual(0.0);
});
