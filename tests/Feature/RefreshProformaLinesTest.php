<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\Setting;

beforeEach(function () {
    Setting::set('InvoiceLinePeriodDisplay', 'period');
});

function proformaInvoice(string $status = 'unpaid'): Invoice
{
    $client = Client::factory()->create(['language' => 'pl']);

    return Invoice::factory()->create([
        'client_id' => $client->id,
        'type' => 'proforma',
        'status' => $status,
        'language' => 'pl',
    ]);
}

function proformaLine(Invoice $invoice, string $type, int $relId, string $due, string $description): InvoiceItem
{
    return InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $invoice->client_id,
        'type' => $type,
        'rel_id' => $relId,
        'due_date' => $due,
        'description' => $description,
        'amount' => 10,
        'qty' => 1,
    ]);
}

it('rewrites hosting periods to the current setting and inclusive end', function () {
    $invoice = proformaInvoice();
    $service = Service::factory()->create(['billing_cycle' => 'Monthly']);
    $monthly = proformaLine($invoice, 'Hosting', $service->id, '2026-12-01', 'Zarządzanie infrastrukturą serwerową (Miesięcznie) — toponline.com.pl — 2026/12/01 - 2027/01/01');

    $annualService = Service::factory()->create(['billing_cycle' => 'Annually']);
    $annual = proformaLine($invoice, 'Hosting', $annualService->id, '2026-12-03', 'Hosting Business (Rocznie) — dentagra.pl — 2026/12/03 - 2027/12/03');

    $biennialService = Service::factory()->create(['billing_cycle' => 'Biennially']);
    $biennial = proformaLine($invoice, 'Hosting', $biennialService->id, '2026-10-03', 'Hosting Individual (Co dwa lata) — sigeo.pl — 2026/10/03 - 2028/10/03');

    $this->artisan('pnlcs:refresh-proforma-lines')->assertSuccessful();

    expect($monthly->fresh()->description)->toBe('Zarządzanie infrastrukturą serwerową — toponline.com.pl — 2026/12/01 - 2026/12/31')
        ->and($annual->fresh()->description)->toBe('Hosting Business — dentagra.pl — 2026/12/03 - 2027/12/02')
        ->and($biennial->fresh()->description)->toBe('Hosting Individual — sigeo.pl — 2026/10/03 - 2028/10/02');
});

it('rewrites addon and domain lines', function () {
    $invoice = proformaInvoice();
    $service = Service::factory()->create(['billing_cycle' => 'Monthly']);
    $productAddon = ProductAddon::create([
        'name' => 'SEO',
        'packages' => null,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 0,
        'tax' => true,
    ]);
    $addon = ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $productAddon->id,
        'client_id' => $service->client_id,
        'qty' => 1,
        'amount' => 10,
        'billing_cycle' => 'Monthly',
        'next_due_date' => '2026-05-01',
        'status' => 'active',
    ]);
    $domain = Domain::factory()->create([
        'client_id' => $invoice->client_id,
        'registration_period' => 1,
        'next_due_date' => '2026-12-03',
    ]);

    $addonLine = proformaLine($invoice, 'Addon', $addon->id, '2026-05-01', 'Addon: SEO – Publiczny adres IP (Miesięcznie) — beedope.pl — 2026/05/01 - 2026/05/31');
    $domainLine = proformaLine($invoice, 'Domain', $domain->id, '2026-12-03', 'Odnowienie domeny: dentagra.pl (1 rok)');

    $this->artisan('pnlcs:refresh-proforma-lines')->assertSuccessful();

    expect($addonLine->fresh()->description)->toBe('Addon: SEO – Publiczny adres IP — beedope.pl — 2026/05/01 - 2026/05/31')
        ->and($domainLine->fresh()->description)->toBe('Odnowienie domeny: dentagra.pl — 2026/12/03 - 2027/12/02');
});

it('repairs a missing separator and an orphaned service using the old cycle label', function () {
    $invoice = proformaInvoice();
    $line = proformaLine($invoice, 'Hosting', 9999, '2026-12-01', 'Dodatkowy adres IP (Miesięcznie)   2026/12/01 - 2027/01/01');

    $this->artisan('pnlcs:refresh-proforma-lines')->assertSuccessful();

    expect($line->fresh()->description)->toBe('Dodatkowy adres IP — 2026/12/01 - 2026/12/31');
});

it('does not touch cancelled proformas in a dry run', function () {
    $invoice = proformaInvoice('cancelled');
    $service = Service::factory()->create(['billing_cycle' => 'Monthly']);
    $line = proformaLine($invoice, 'Hosting', $service->id, '2026-12-01', 'Hosting (Miesięcznie) — example.test — 2026/12/01 - 2027/01/01');

    $this->artisan('pnlcs:refresh-proforma-lines', ['--dry-run' => true])->assertSuccessful();
    expect($line->fresh()->description)->toBe('Hosting (Miesięcznie) — example.test — 2026/12/01 - 2027/01/01');

    $this->artisan('pnlcs:refresh-proforma-lines')->assertSuccessful();
    expect($line->fresh()->description)->toBe('Hosting (Miesięcznie) — example.test — 2026/12/01 - 2027/01/01');
});

it('keeps the cycle when the setting asks for both parts', function () {
    Setting::set('InvoiceLinePeriodDisplay', 'cycle_and_period');
    $invoice = proformaInvoice();
    $service = Service::factory()->create(['billing_cycle' => 'Monthly']);
    $line = proformaLine($invoice, 'Hosting', $service->id, '2026-12-01', 'Hosting (Miesięcznie) — example.test — 2026/12/01 - 2027/01/01');

    $this->artisan('pnlcs:refresh-proforma-lines')->assertSuccessful();

    expect($line->fresh()->description)->toBe('Hosting (Miesięcznie) — example.test — 2026/12/01 - 2026/12/31');
});

it('does not save an open proforma line in a dry run', function () {
    $invoice = proformaInvoice();
    $service = Service::factory()->create(['billing_cycle' => 'Monthly']);
    $line = proformaLine($invoice, 'Hosting', $service->id, '2026-12-01', 'Hosting (Miesięcznie) — example.test — 2026/12/01 - 2027/01/01');

    $this->artisan('pnlcs:refresh-proforma-lines', ['--dry-run' => true])->assertSuccessful();

    expect($line->fresh()->description)->toBe('Hosting (Miesięcznie) — example.test — 2026/12/01 - 2027/01/01');
});

it('keeps only the cycle when that is the selected setting', function () {
    Setting::set('InvoiceLinePeriodDisplay', 'cycle');
    $invoice = proformaInvoice();
    $service = Service::factory()->create(['billing_cycle' => 'Monthly']);
    $line = proformaLine($invoice, 'Hosting', $service->id, '2026-12-01', 'Hosting (Miesięcznie) — example.test — 2026/12/01 - 2027/01/01');

    $this->artisan('pnlcs:refresh-proforma-lines')->assertSuccessful();

    expect($line->fresh()->description)->toBe('Hosting (Miesięcznie) — example.test');
});

it('puts a first-period note after the rebuilt period', function () {
    $invoice = proformaInvoice();
    $service = Service::factory()->create(['billing_cycle' => 'Monthly']);
    $productAddon = ProductAddon::create([
        'name' => 'Dodatkowy IP SEO',
        'packages' => null,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 0,
        'tax' => true,
    ]);
    $addon = ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $productAddon->id,
        'client_id' => $service->client_id,
        'qty' => 1,
        'amount' => 10,
        'prorated' => true,
        'proration_days' => 14,
        'proration_cycle_days' => 30,
        'billing_cycle' => 'Monthly',
        'next_due_date' => '2026-12-06',
        'status' => 'active',
    ]);
    $line = proformaLine($invoice, 'Addon', $addon->id, '2026-12-06', 'Addon: Dodatkowy IP SEO (Miesięcznie) — 24box.pl — pierwszy okres: 14 z 30 dni');

    $this->artisan('pnlcs:refresh-proforma-lines')->assertSuccessful();

    expect($line->fresh()->description)->toBe('Addon: Dodatkowy IP SEO — 24box.pl — 2026/12/06 - 2027/01/05 — pierwszy okres: 14 z 30 dni');
});
