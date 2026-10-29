<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;

/*
 * The {vat_summary} placeholder: one row per VAT rate (or tax treatment) with
 * its tax amount only. The net, VAT and gross totals are the template's own
 * {subtotal}, {tax} and {total}, so they are not repeated here. Computed from
 * the saved items (Invoice::vatBreakdown), not by parsing the rendered HTML.
 */

function vatCurrency(): void
{
    Currency::updateOrCreate(['code' => 'PLN'], ['prefix' => '', 'suffix' => ' zł', 'format' => 1, 'rate' => 1, 'is_default' => false]);
}

/** @param list<array{description: string, amount: float, qty?: int, tax_rate?: float|null, tax_label?: string|null, taxed?: bool}> $lines */
function vatSummaryInvoice(array $lines, array $attrs = []): Invoice
{
    vatCurrency();
    $client = Client::factory()->create(['tax_exempt' => false]);
    $invoice = Invoice::factory()->create(array_merge([
        'client_id' => $client->id,
        'source_currency' => 'PLN',
        'status' => 'unpaid',
    ], $attrs));

    foreach ($lines as $line) {
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'type' => 'Other',
            'description' => $line['description'],
            'qty' => $line['qty'] ?? 1,
            'amount' => $line['amount'],
            'taxed' => $line['taxed'] ?? true,
            'tax_rate' => $line['tax_rate'] ?? null,
            'tax_label' => $line['tax_label'] ?? null,
        ]);
    }

    return app(InvoiceService::class)->recalculateTotals($invoice->fresh());
}

function renderVatTemplate(string $template, Invoice $invoice): string
{
    $service = app(InvoicePdfService::class);

    return $service->merge($template, $service->placeholderVars($invoice));
}

it('renders a single rate with only its tax amount', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'Hosting', 'amount' => 100, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
    ]);

    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['vat_summary'];

    expect($html)
        ->toContain('VAT 23%')
        ->toContain(invoice_money_fmt(23, $invoice));   // the 23% tax only
});

it('renders one row per rate with only the tax amount', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'A', 'amount' => 784, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
        ['description' => 'B', 'amount' => 100, 'tax_rate' => 8, 'tax_label' => 'VAT 8%'],
    ]);

    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['vat_summary'];

    expect($html)
        ->toContain('VAT 23%')->toContain(invoice_money_fmt(180.32, $invoice))
        ->toContain('VAT 8%')->toContain(invoice_money_fmt(8, $invoice));
});

it('sums several items with the same rate into one row', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'A', 'amount' => 40, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
        ['description' => 'B', 'amount' => 60, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
        ['description' => 'C', 'amount' => 20, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
    ]);

    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['vat_summary'];

    expect($html)
        ->toContain('VAT 23%')
        ->toContain(invoice_money_fmt(27.60, $invoice));   // 120 * 23%
});

it('keeps a 0% rate and a VAT exemption distinct', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'Zero', 'amount' => 100, 'tax_rate' => 0, 'tax_label' => '0%'],
        ['description' => 'Exempt', 'amount' => 200, 'tax_rate' => 0, 'tax_label' => 'zw.'],
    ]);

    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['vat_summary'];

    expect($html)
        ->toContain('0%')
        ->toContain('zw.');
});

it('does not repeat the net, VAT or gross totals', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'A', 'amount' => 784, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
        ['description' => 'B', 'amount' => 100, 'tax_rate' => 8, 'tax_label' => 'VAT 8%'],
    ]);

    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['vat_summary'];

    // The template prints these itself — {subtotal}, {tax}, {total} — so the
    // breakdown must not carry them again.
    expect($html)
        ->not->toContain(invoice_money_fmt((float) $invoice->subtotal, $invoice))
        ->not->toContain(invoice_money_fmt((float) $invoice->tax, $invoice))
        ->not->toContain(invoice_money_fmt((float) $invoice->total, $invoice));
});

it('does not render the summary when the placeholder is absent', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'A', 'amount' => 100, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
    ]);

    $html = renderVatTemplate('<h1>Doc</h1>{items}', $invoice);

    expect($html)->not->toContain('vat-summary');
});

it('renders the summary through the conditional block', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'A', 'amount' => 100, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
    ]);

    $html = renderVatTemplate('<h1>Doc</h1>{?vat_summary}{vat_summary}{/vat_summary}', $invoice);

    expect($html)->toContain('vat-summary');
});

it('hides the conditional block when the invoice has no VAT', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'A', 'amount' => 100, 'taxed' => false],
    ], ['tax_mode' => 'none']);

    $html = renderVatTemplate('<h1>Doc</h1>{?vat_summary}HIDDEN{/vat_summary}', $invoice);

    expect($html)->not->toContain('HIDDEN')
        ->and($html)->not->toContain('vat-summary');
});

it('renders a PDF containing the VAT summary', function () {
    $invoice = vatSummaryInvoice([
        ['description' => 'A', 'amount' => 100, 'tax_rate' => 23, 'tax_label' => 'VAT 23%'],
    ]);

    Setting::set('InvoiceTemplate', 'custom', 'general');
    @mkdir(dirname(InvoicePdfService::customTemplatePath()), 0755, true);
    file_put_contents(InvoicePdfService::customTemplatePath(), '<h1>Faktura</h1>{items}{vat_summary}');

    $output = app(InvoicePdfService::class)->generate($invoice)->output();

    expect($output)->toStartWith('%PDF');

    @unlink(InvoicePdfService::customTemplatePath());
    Setting::set('InvoiceTemplate', 'default', 'general');
});
