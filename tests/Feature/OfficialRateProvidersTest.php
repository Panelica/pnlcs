<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\OfficialRateProviders;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Official rate sources the operator adds next to TCMB: a fresh table is
 * applied with its provenance, a stale one is refused or used as the entry
 * says, a missing one leaves the commercial rate, and a table quoted the other
 * way round is inverted because the entry says so.
 */
function providerShop(array $overrides = []): void
{
    Currency::updateOrCreate(['code' => 'USD'], ['name' => 'USD', 'prefix' => '$', 'rate' => 1.0, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'AED'], ['name' => 'AED', 'prefix' => 'AED ', 'rate' => 3.5, 'is_default' => false]);
    Setting::set('BillingCurrency', 'AED');
    Setting::set('OfficialRateProviders', json_encode([array_merge([
        'key' => 'standin',
        'name' => 'Stand-in Central Bank',
        'url' => 'https://bank.test/rates',
        'base' => 'USD',
        'currency' => 'AED',
        'rate_path' => 'rate',
        'date_path' => 'rate_date',
        'stale_path' => 'stale',
        'ref_path' => 'table',
        'quote' => 'base',
        'kind' => 'reference',
        'on_stale' => 'refuse',
    ], $overrides)]));
    Setting::set('OfficialRateProvider', 'standin');
}

function fakeProvider(int $status, array $body = []): void
{
    Http::fake([
        'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['AED' => 3.6]], 200),
        'api.frankfurter.app/*' => Http::response(['rates' => ['AED' => 3.6]], 200),
        'bank.test/*' => Http::response($body, $status),
    ]);
}

function providerInvoice(): Invoice
{
    return Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'subtotal' => 10, 'total' => 10]);
}

it('applies a fresh table and stamps its provenance on the invoice', function () {
    providerShop();
    fakeProvider(200, ['rate' => 3.6725, 'rate_date' => '2026-10-30', 'stale' => false, 'table' => 'T-212']);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();
    $invoice = providerInvoice();

    expect((float) Currency::where('code', 'AED')->value('rate'))->toBe(3.6725)
        ->and($invoice->exchange_rate_source)->toBe('Stand-in Central Bank')
        ->and($invoice->exchange_rate_kind)->toBe('reference')
        ->and($invoice->exchange_rate_date)->toBe('2026-10-30')
        ->and($invoice->exchange_rate_ref)->toBe('T-212');
});

it('refuses a stale table and names no source', function () {
    providerShop();
    fakeProvider(200, ['rate' => 3.70, 'rate_date' => '2026-09-01', 'stale' => true]);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();
    $invoice = providerInvoice();

    expect((float) Currency::where('code', 'AED')->value('rate'))->toBe(3.6)
        ->and($invoice->exchange_rate_source)->toBeNull();
});

it('uses a stale table when the entry says so', function () {
    providerShop(['on_stale' => 'use']);
    fakeProvider(200, ['rate' => 3.70, 'rate_date' => '2026-09-01', 'stale' => true]);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    expect((float) Currency::where('code', 'AED')->value('rate'))->toBe(3.7)
        ->and(providerInvoice()->exchange_rate_date)->toBe('2026-09-01');
});

it('treats an old date as stale when max_age_days is set', function () {
    providerShop(['stale_path' => '', 'max_age_days' => 5]);
    fakeProvider(200, ['rate' => 3.70, 'rate_date' => now()->subDays(9)->toDateString()]);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    expect(providerInvoice()->exchange_rate_source)->toBeNull();
});

it('keeps the commercial rate when the table is missing', function () {
    providerShop();
    fakeProvider(404);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    expect((float) Currency::where('code', 'AED')->value('rate'))->toBe(3.6)
        ->and(providerInvoice()->exchange_rate_source)->toBeNull();
});

it('inverts a table quoted the other way round', function () {
    providerShop(['quote' => 'currency']);
    fakeProvider(200, ['rate' => 0.2723, 'rate_date' => '2026-10-30', 'stale' => false]);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    expect((float) Currency::where('code', 'AED')->value('rate'))->toBe(round(1 / 0.2723, 5));
});

it('sends the API key only to entries marked auth', function () {
    providerShop(['auth' => true]);
    Setting::set('OfficialRateApiKey', 'secret-key');
    fakeProvider(200, ['rate' => 3.6725, 'rate_date' => '2026-10-30', 'stale' => false]);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'bank.test') && $r->hasHeader('Authorization', 'Bearer secret-key'));
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'open.er-api.com') && $r->hasHeader('Authorization'));
});

it('does not send the API key to entries without auth', function () {
    providerShop();
    Setting::set('OfficialRateApiKey', 'secret-key');
    fakeProvider(200, ['rate' => 3.6725, 'rate_date' => '2026-10-30', 'stale' => false]);

    $this->artisan('pnlcs:currency-update')->assertSuccessful();

    Http::assertNotSent(fn (Request $r) => $r->hasHeader('Authorization'));
});

it('rejects an entry that does not say how to read its answer', function (array $entry, string $needle) {
    [, $errors] = OfficialRateProviders::normalise([array_merge([
        'key' => 'x', 'name' => 'X', 'url' => 'https://bank.test', 'base' => 'USD', 'currency' => 'AED',
        'rate_path' => 'rate', 'date_path' => 'rate_date', 'quote' => 'base',
    ], $entry)]);

    expect(implode(' ', $errors))->toContain($needle);
})->with([
    'no quote direction' => [['quote' => ''], 'quote'],
    'plain http' => [['url' => 'http://bank.test'], 'https'],
    'no date path' => [['date_path' => ''], 'date_path'],
    'same currencies' => [['currency' => 'USD'], 'base and currency'],
    'reserved key' => [['key' => 'tcmb'], 'key'],
]);

it('ignores a stored list that no longer validates', function () {
    Setting::set('OfficialRateProviders', '[{"key":"x"}]');

    expect(OfficialRateProviders::all())->toBe([]);
});
