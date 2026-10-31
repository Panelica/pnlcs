<?php

use App\Events\InvoiceCreated;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\ProformaService;
use Illuminate\Support\Facades\Event;

/*
 * The VAT invoice issued for a paid proforma must say which proforma it was
 * issued for, in the notes ("Do proformy nr: <number>" in Polish). The number
 * is read off the proforma itself, existing notes are kept, and the line is
 * written once no matter how often the process runs.
 */

beforeEach(fn () => Event::fake([InvoiceCreated::class]));

function noteProforma(array $attrs = [], string $language = 'pl'): Invoice
{
    $client = Client::factory()->create(['language' => $language]);
    $proforma = Invoice::factory()->create(array_merge([
        'client_id' => $client->id,
        'type' => 'proforma',
        'status' => 'paid',
        'invoice_num' => 'PRO-2026/10-000002',
        'subtotal' => 10,
        'total' => 10,
    ], $attrs));
    InvoiceItem::create([
        'invoice_id' => $proforma->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => 10,
        'taxed' => false,
    ]);

    return $proforma->fresh();
}

test('the VAT invoice notes name the proforma it was issued for', function () {
    $proforma = noteProforma();

    $vat = app(ProformaService::class)->issueVatInvoice($proforma);

    expect($vat->notes)->toBe('Do proformy nr: PRO-2026/10-000002');
});

test('the reference is written in the customer\'s language', function () {
    $proforma = noteProforma([], 'en');

    $vat = app(ProformaService::class)->issueVatInvoice($proforma);

    expect($vat->notes)->toBe('For proforma no.: PRO-2026/10-000002');
});

test('existing proforma notes are kept with the reference appended', function () {
    $proforma = noteProforma(['notes' => 'Kontakt: Jan Kowalski']);

    $vat = app(ProformaService::class)->issueVatInvoice($proforma);

    expect($vat->notes)->toBe("Kontakt: Jan Kowalski\nDo proformy nr: PRO-2026/10-000002");
});

test('issuing the VAT invoice twice writes the reference once', function () {
    $proforma = noteProforma();

    $first = app(ProformaService::class)->issueVatInvoice($proforma);
    $second = app(ProformaService::class)->issueVatInvoice($proforma);

    expect($second->id)->toBe($first->id)
        ->and($second->notes)->toBe('Do proformy nr: PRO-2026/10-000002');
});
