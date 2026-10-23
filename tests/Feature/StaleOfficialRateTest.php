<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * The TCMB bulletin details (source, kind, date, bulletin number) were written
 * when the official rate was fetched and never cleared. When TCMB was down, or
 * the operator switched the official source off, the lira rate came from the
 * commercial provider while invoices kept printing the old bulletin under it.
 */
function staleRateShop(): void
{
    $GLOBALS['ratesFaked'] = false;
    Currency::updateOrCreate(['code' => 'USD'], ['name' => 'USD', 'prefix' => '$', 'rate' => 1.0, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'TRY'], ['name' => 'TRY', 'prefix' => 'TRY ', 'rate' => 30.0, 'is_default' => false]);
    Setting::set('BillingCurrency', 'TRY');
    Setting::set('OfficialRateProvider', 'tcmb');
}

function fakeRates(int $tcmbStatus): void
{
    // One fake for the whole test (a second Http::fake only adds stubs behind
    // the first); the TCMB answer follows the latest call.
    $GLOBALS['tcmbStatus'] = $tcmbStatus;

    if ($GLOBALS['ratesFaked'] ?? false) {
        return;
    }
    $GLOBALS['ratesFaked'] = true;

    Http::fake([
        'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['TRY' => 33.0]], 200),
        'api.frankfurter.app/*' => Http::response(['rates' => ['TRY' => 33.0]], 200),
        'www.tcmb.gov.tr/*' => fn () => $GLOBALS['tcmbStatus'] === 200
            ? Http::response('<Tarih_Date Tarih="09.09.2026" Bulten_No="2026/170"><Currency Kod="USD"><Unit>1</Unit><ForexSelling>41,2000</ForexSelling></Currency></Tarih_Date>', 200)
            : Http::response('', $GLOBALS['tcmbStatus']),
    ]);
}

function newInvoice(): Invoice
{
    return Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'subtotal' => 10, 'total' => 10]);
}

it('stops naming the bulletin when TCMB could not be reached', function () {
    staleRateShop();
    fakeRates(200);
    $this->artisan('pnlcs:currency-update');
    expect(newInvoice()->exchange_rate_ref)->toBe('2026/170');

    fakeRates(500);
    $this->artisan('pnlcs:currency-update');
    $invoice = newInvoice();

    expect((float) $invoice->exchange_rate)->toBe(33.0)
        ->and($invoice->exchange_rate_ref)->toBeNull()
        ->and($invoice->exchange_rate_source)->toBeNull();
});

it('stops naming the bulletin when the official source is switched off', function () {
    staleRateShop();
    fakeRates(200);
    $this->artisan('pnlcs:currency-update');

    Setting::set('OfficialRateProvider', '');
    $this->artisan('pnlcs:currency-update');

    expect(newInvoice()->exchange_rate_ref)->toBeNull();
});

it('does not put the lira bulletin on an invoice billed in another currency', function () {
    staleRateShop();
    fakeRates(200);
    $this->artisan('pnlcs:currency-update');

    Currency::updateOrCreate(['code' => 'EUR'], ['name' => 'EUR', 'prefix' => '€', 'rate' => 0.9, 'is_default' => false]);
    Setting::set('BillingCurrency', 'EUR');
    $invoice = newInvoice();

    expect($invoice->billing_currency)->toBe('EUR')
        ->and($invoice->exchange_rate_ref)->toBeNull();
});
