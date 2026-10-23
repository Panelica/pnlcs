<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use Modules\Ksef\Support\InvoiceXmlBuilder;

/**
 * The FA(2) seller address read the Address1/Address2 settings, which no
 * screen writes; General Settings saves Address, Postcode and CompanyCity.
 * Every seller's address came out as "Polska".
 */
function ksefXml(): string
{
    $client = Client::factory()->create(['country' => 'PL']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 100, 'total' => 100]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Other', 'description' => 'Hosting', 'qty' => 1, 'amount' => 100, 'taxed' => false]);

    return (new InvoiceXmlBuilder)->build($invoice, '1234567890');
}

it('puts the General Settings address on the e-invoice', function () {
    Setting::set('Address', "ul. Prosta 1\nlok. 4");
    Setting::set('Postcode', '00-001');
    Setting::set('CompanyCity', 'Warszawa');

    expect(ksefXml())->toContain('<AdresL1>ul. Prosta 1, lok. 4, 00-001 Warszawa</AdresL1>');
});

it('still writes Polska when no address is set', function () {
    expect(ksefXml())->toContain('<AdresL1>Polska</AdresL1>');
});
