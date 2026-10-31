<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\File;

/*
 * Multilingual invoice templates: a file named
 * "<LANG>_invoice_template_v<VER>.html" is picked for the invoice's own
 * language (frozen at issue time), with English as the fallback. Language and
 * version are parsed off the filename — there is no database behind them.
 */

function tplDir(): string
{
    return dirname(InvoicePdfService::customTemplatePath());
}

function makeLanguageTpl(string $language, string $version): string
{
    File::ensureDirectoryExists(tplDir());
    $path = tplDir().'/'.strtoupper($language).'_invoice_template_v'.$version.'.html';
    File::put($path, '<html>{items}{vat_summary}{company_bank_accounts}</html>');

    return $path;
}

beforeEach(function () {
    File::ensureDirectoryExists(tplDir());
    File::cleanDirectory(tplDir());
});

test('parses the language and version from a template filename', function () {
    expect(InvoicePdfService::parseTemplateFilename('PL_invoice_template_v1.32.html'))
        ->toBe(['language' => 'PL', 'version' => '1.32'])
        ->and(InvoicePdfService::parseTemplateFilename('en_invoice_template_v1.00.html'))
        ->toBe(['language' => 'EN', 'version' => '1.00'])
        ->and(InvoicePdfService::parseTemplateFilename('custom.html'))
        ->toBeNull();
});

test('picks the highest version template for the invoice language', function () {
    makeLanguageTpl('pl', '1.00');
    $newer = makeLanguageTpl('pl', '1.32');
    makeLanguageTpl('en', '1.00');

    $plInvoice = Invoice::factory()->create(['language' => 'pl']);
    $enInvoice = Invoice::factory()->create(['language' => 'en']);

    $service = app(InvoicePdfService::class);

    expect($service->customTemplate($plInvoice))->toBe($newer)
        ->and($service->customTemplate($enInvoice))->toBe(tplDir().'/EN_invoice_template_v1.00.html');
});

test('falls back to the English template when the language has none', function () {
    makeLanguageTpl('en', '1.00');

    $deInvoice = Invoice::factory()->create(['language' => 'de']);

    expect(app(InvoicePdfService::class)->customTemplate($deInvoice))->toBe(tplDir().'/EN_invoice_template_v1.00.html');
});

test('returns null when no language template exists', function () {
    $deInvoice = Invoice::factory()->create(['language' => 'de']);

    expect(app(InvoicePdfService::class)->customTemplate($deInvoice))->toBeNull();
});

test('lists every required placeholder in the vocabulary', function () {
    $vocabulary = InvoicePdfService::customTemplateVocabulary();

    foreach ([
        'company_name', 'company_logo', 'company_bank_accounts',
        'client_name', 'client_company',
        'invoice_number', 'subtotal', 'tax', 'total',
        'items', 'vat_summary', 'ksef_number', 'ksef_qr', 'payment_qr',
    ] as $placeholder) {
        expect($vocabulary)->toContain($placeholder);
    }
});

test('an invoice snapshots the client language at issue time', function () {
    $client = Client::factory()->create(['language' => 'en']);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 10, 'taxed' => false],
    ]);

    expect($invoice->language)->toBe('en');
});

test('the VAT summary labels follow the invoice language', function () {
    $service = app(InvoicePdfService::class);

    $pl = Invoice::factory()->create(['language' => 'pl', 'subtotal' => 100, 'tax' => 23, 'total' => 123]);
    $en = Invoice::factory()->create(['language' => 'en', 'subtotal' => 100, 'tax' => 23, 'total' => 123]);

    expect($service->placeholderVars($pl)['invoice_type'])->toBe(__('pdf.invoice', [], 'pl'))
        ->and($service->placeholderVars($en)['invoice_type'])->toBe(__('pdf.invoice', [], 'en'));
});
