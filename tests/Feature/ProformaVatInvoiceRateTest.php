<?php

use App\Events\InvoiceCreated;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Services\ProformaService;
use Illuminate\Support\Facades\Event;

/*
 * The VAT invoice for a paid proforma was stamped with the rate of the day it
 * was issued, not the proforma's. Everything else is copied across, but the
 * currency stamp was left to the model, which treats a new invoice as a new
 * sale: a customer billed in lira paid the proforma at 40 and got a legal
 * invoice at 45, with another day's TCMB bulletin on it.
 */

beforeEach(fn () => Event::fake([InvoiceCreated::class]));

function pvrCurrency(string $code, float $rate, bool $default = false): void
{
    Currency::updateOrCreate(['code' => $code], ['name' => $code, 'prefix' => $code.' ', 'rate' => $rate, 'is_default' => $default]);
}

function pvrProforma(array $attrs = []): Invoice
{
    $client = Client::factory()->create(['country' => 'TR', 'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000']);
    $proforma = Invoice::factory()->create(array_merge(['client_id' => $client->id, 'type' => 'proforma', 'status' => 'paid', 'subtotal' => 10, 'total' => 10], $attrs));
    InvoiceItem::create(['invoice_id' => $proforma->id, 'client_id' => $client->id, 'type' => 'Hosting', 'description' => 'Hosting', 'amount' => 10, 'taxed' => false]);

    return $proforma->fresh();
}

test('the VAT invoice keeps the rate and bulletin the proforma was paid at', function () {
    pvrCurrency('EUR', 1.0, true);
    pvrCurrency('TRY', 40.0);
    Setting::set('BillingCurrency', 'TRY');
    Setting::set('ExchangeRateSource', 'TCMB');
    Setting::set('ExchangeRateDate', '09.09.2026');
    Setting::set('ExchangeRateBulletin', '2026/170');
    $proforma = pvrProforma();

    // A week later: a new rate, a new bulletin.
    Currency::where('code', 'TRY')->update(['rate' => 45]);
    Setting::set('ExchangeRateDate', '16.09.2026');
    Setting::set('ExchangeRateBulletin', '2026/175');

    $vat = app(ProformaService::class)->issueVatInvoice($proforma);

    expect($vat->billing_currency)->toBe('TRY')
        ->and((float) $vat->exchange_rate)->toBe(40.0)
        ->and($vat->exchange_rate_date)->toBe('09.09.2026')
        ->and($vat->exchange_rate_ref)->toBe('2026/170')
        ->and(billing_money_fmt(10, $vat))->toBe(billing_money_fmt(10, $proforma));
});

test('a proforma in the shop currency gives a VAT invoice in the shop currency, whatever is set since', function () {
    pvrCurrency('EUR', 1.0, true);
    pvrCurrency('TRY', 45.0);
    Setting::set('BillingCurrency', '');
    $proforma = pvrProforma();
    expect($proforma->billing_currency)->toBeNull();

    Setting::set('BillingCurrency', 'TRY');
    $vat = app(ProformaService::class)->issueVatInvoice($proforma);

    expect(has_billing_conversion($vat))->toBeFalse()
        ->and($vat->source_currency)->toBe('EUR');
});
