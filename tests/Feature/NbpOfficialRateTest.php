<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/*
 * The National Bank of Poland as the official source: with the shop in PLN its
 * table A drives every currency, the commercial provider only fills the codes
 * NBP does not publish, and an invoice in the billing currency prints the NBP
 * date and table number.
 */

function nbpShop(): void
{
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => 'PLN'], ['prefix' => '', 'suffix' => ' zł', 'format' => 1, 'rate' => 1, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => ' EUR', 'format' => 1, 'rate' => 0.2, 'is_default' => false]);
    Currency::updateOrCreate(['code' => 'GBP'], ['prefix' => '£', 'suffix' => ' GBP', 'format' => 1, 'rate' => 0.18, 'is_default' => false]);
    Currency::updateOrCreate(['code' => 'AED'], ['prefix' => 'AED ', 'suffix' => '', 'format' => 1, 'rate' => 3.7, 'is_default' => false]);
    Setting::set('BillingCurrency', 'EUR');
    Setting::set('OfficialRateProvider', 'nbp');
    app()->forgetInstance('pnlcs.currency');
}

function fakeNbpRates(): void
{
    Http::fake([
        'api.nbp.pl/*' => Http::response([[
            'table' => 'A',
            'no' => '217/A/NBP/2026',
            'effectiveDate' => '2026-11-06',
            'rates' => [
                ['code' => 'EUR', 'mid' => 4.30],
                ['code' => 'GBP', 'mid' => 5.00],
            ],
        ]], 200),
        'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['AED' => 3.67]], 200),
        'api.frankfurter.app/*' => Http::response(['rates' => ['AED' => 3.67]], 200),
    ]);
}

function nbpInvoice(): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'unpaid', 'subtotal' => 10, 'total' => 10,
    ]);
}

it('takes every shop currency from NBP and stamps the billing currency', function () {
    nbpShop();
    fakeNbpRates();

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    expect((float) Currency::where('code', 'EUR')->value('rate'))->toBe(round(1 / 4.30, 5))
        ->and((float) Currency::where('code', 'GBP')->value('rate'))->toBe(round(1 / 5.00, 5))
        ->and((float) Currency::where('code', 'AED')->value('rate'))->toBe(3.67);

    $invoice = nbpInvoice();

    expect($invoice->exchange_rate_source)->toBe('NBP')
        ->and($invoice->exchange_rate_kind)->toBe('reference')
        ->and($invoice->exchange_rate_date)->toBe('2026-11-06')
        ->and($invoice->exchange_rate_ref)->toBe('217/A/NBP/2026');
});

it('falls back to the commercial provider when NBP is unreachable', function () {
    nbpShop();
    Http::fake([
        'api.nbp.pl/*' => Http::response('', 500),
        'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['EUR' => 0.2, 'GBP' => 0.18, 'AED' => 3.67]], 200),
        'api.frankfurter.app/*' => Http::response(['rates' => ['EUR' => 0.2, 'GBP' => 0.18, 'AED' => 3.67]], 200),
    ]);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    expect((float) Currency::where('code', 'EUR')->value('rate'))->toBe(0.2);
    expect(nbpInvoice()->exchange_rate_source)->toBeNull();
});

it('does not call NBP when the shop currency is not the złoty', function () {
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'format' => 1, 'rate' => 1, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'format' => 1, 'rate' => 0.2, 'is_default' => false]);
    Setting::set('BillingCurrency', 'EUR');
    Setting::set('OfficialRateProvider', 'nbp');
    app()->forgetInstance('pnlcs.currency');

    Http::fake([
        'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['EUR' => 0.19]], 200),
        'api.frankfurter.app/*' => Http::response(['rates' => ['EUR' => 0.19]], 200),
    ]);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    expect((float) Currency::where('code', 'EUR')->value('rate'))->toBe(0.19);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.nbp.pl'));
});

it('offers NBP next to TCMB in the settings', function () {
    $role = AdminRole::factory()->fullAdmin()->create();

    $this->actingAs(Admin::factory()->create(['role_id' => $role->id]), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk()
        ->assertSee('value="nbp"', false)
        ->assertSee(__('admin.settings.official_rate_nbp'));
});
