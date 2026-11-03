<?php

use App\Enums\InvoiceStatus;
use App\Events\InvoiceCreated;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceService;
use App\Services\ProformaService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * The VAT invoice for a paid proforma names the buyer frozen on the proforma,
 * not the client record as it stands when the money arrives: the document the
 * money came from decides who was billed.
 */

beforeEach(function () {
    Mail::fake();
    Event::fake([InvoiceCreated::class]);
});

function buyerProforma(Client $client): Invoice
{
    $proforma = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Other', 'description' => 'Work', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
    ], ['type' => 'proforma']);
    $proforma->update(['status' => InvoiceStatus::Paid->value]);

    return $proforma;
}

test('a buyer who became exempt after the proforma is not marked exempt on its VAT invoice', function () {
    $client = Client::factory()->create(['tax_exempt' => false]);
    $proforma = buyerProforma($client);

    $client->update(['tax_exempt' => true]);

    $vat = app(ProformaService::class)->issueVatInvoice($proforma->fresh())->fresh();

    expect((bool) $vat->buyer_tax_exempt)->toBeFalse()
        ->and((float) $vat->tax)->toEqual(20.0)
        ->and((float) $vat->total)->toEqual(120.0)
        ->and((float) app(InvoiceService::class)->recalculateTotals($vat)->total)->toEqual(120.0);
});

test('the VAT invoice names the buyer of the proforma after the client changed their details', function () {
    $client = Client::factory()->create([
        'first_name' => 'Jan', 'last_name' => 'Kowalski', 'company_name' => 'Kowalski Sp. z o.o.',
        'address1' => 'ul. Stara 1', 'city' => 'Kraków', 'postcode' => '30-001', 'country' => 'PL',
        'tax_id' => '6762465910',
    ]);
    $proforma = buyerProforma($client);

    $client->update([
        'first_name' => 'Anna', 'last_name' => 'Nowak', 'company_name' => 'Nowak S.A.',
        'address1' => 'ul. Nowa 2', 'city' => 'Gdańsk', 'postcode' => '80-001',
        'tax_id' => '5842734981',
    ]);

    $vat = app(ProformaService::class)->issueVatInvoice($proforma->fresh())->fresh();

    expect($vat->buyer('first_name'))->toBe('Jan')
        ->and($vat->buyer('last_name'))->toBe('Kowalski')
        ->and($vat->buyer('company_name'))->toBe('Kowalski Sp. z o.o.')
        ->and($vat->buyer('address1'))->toBe('ul. Stara 1')
        ->and($vat->buyer('city'))->toBe('Kraków')
        ->and($vat->buyer('postcode'))->toBe('30-001')
        ->and($vat->buyer('tax_id'))->toBe('6762465910');
});

test('a VAT invoice for an unchanged client carries the same buyer as its proforma', function () {
    $client = Client::factory()->create([
        'tax_exempt' => true, 'client_type' => 'company', 'company_name' => 'Kowalski Sp. z o.o.',
        'address2' => 'lok. 5', 'state' => 'Małopolskie', 'tax_id' => '6762465910',
        'tax_office' => 'Kraków-Krowodrza', 'national_id' => '12345678901',
    ]);
    $proforma = buyerProforma($client);

    $vat = app(ProformaService::class)->issueVatInvoice($proforma->fresh())->fresh();

    foreach ([
        'first_name', 'last_name', 'company_name', 'email', 'address1', 'address2', 'city', 'state',
        'postcode', 'country', 'tax_id', 'tax_office', 'national_id', 'client_type', 'custom_fields',
    ] as $field) {
        expect($vat->{'buyer_'.$field})->toBe($proforma->fresh()->{'buyer_'.$field});
    }
    expect((bool) $vat->buyer_tax_exempt)->toBeTrue();
});

test('a proforma with no buyer snapshot gives its VAT invoice the client at issue time', function () {
    $client = Client::factory()->create(['tax_exempt' => false, 'address1' => 'ul. Stara 1', 'tax_id' => '6762465910']);
    // Guard for a proforma whose buyer columns are all empty: nothing to copy.
    $proforma = Invoice::factory()->create([
        'client_id' => $client->id, 'type' => 'proforma', 'status' => InvoiceStatus::Paid->value,
        'subtotal' => 100, 'total' => 100,
    ]);
    InvoiceItem::create([
        'invoice_id' => $proforma->id, 'client_id' => $client->id,
        'type' => 'Other', 'description' => 'Work', 'amount' => 100, 'taxed' => false,
    ]);
    expect($proforma->fresh()->buyer_address1)->toBeNull();

    $client->update(['address1' => 'ul. Nowa 2', 'tax_id' => '5842734981', 'tax_exempt' => true]);

    $vat = app(ProformaService::class)->issueVatInvoice($proforma->fresh())->fresh();

    expect($vat->buyer_address1)->toBe('ul. Nowa 2')
        ->and($vat->buyer_tax_id)->toBe('5842734981')
        ->and((bool) $vat->buyer_tax_exempt)->toBeTrue();
});

test('a column the proforma left empty is read from the client, as the proforma shows it', function () {
    $client = Client::factory()->create(['address1' => 'ul. Stara 1', 'address2' => null]);
    $proforma = buyerProforma($client);

    $client->update(['address2' => 'lok. 5']);
    expect($proforma->fresh()->buyer('address2'))->toBe('lok. 5');

    $vat = app(ProformaService::class)->issueVatInvoice($proforma->fresh())->fresh();

    expect($vat->buyer_address2)->toBe('lok. 5')
        ->and($vat->buyer_address1)->toBe('ul. Stara 1');
});
