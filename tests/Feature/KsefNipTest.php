<?php

use App\Models\AddonSetting;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Modules\Ksef\KsefSettings;
use Modules\Ksef\Support\InvoiceXmlBuilder;

it('writes seller and buyer NIPs without the PL prefix', function () {
    $client = Client::factory()->create(['country' => 'PL', 'tax_id' => 'PL6482409327']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'buyer_tax_id' => 'PL6482409327',
        'status' => 'unpaid',
        'subtotal' => 100,
        'total' => 100,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Other',
        'description' => 'Hosting',
        'qty' => 1,
        'amount' => 100,
        'taxed' => false,
    ]);

    $xml = (new InvoiceXmlBuilder)->build($invoice, 'PL6482409327');

    expect($xml)->toContain('<NIP>6482409327</NIP>')
        ->not->toContain('<NIP>PL');
});

it('normalises the configured seller NIP for KSeF authentication', function () {
    AddonSetting::setSetting('ksef', 'nip', 'PL6482409327');

    expect(KsefSettings::resolve()['nip'])->toBe('6482409327')
        ->and(KsefSettings::configured())->toBeFalse();
});
