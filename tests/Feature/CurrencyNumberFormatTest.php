<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Setting;
use App\Support\CustomerCurrency;

/*
 * The number format of a currency.
 *
 * Every amount was written 1,234.56, whatever the currency. A lira or euro
 * price reads 1.234,56 in Turkey and much of Europe, so the customer had to
 * translate the number before reading the price. The currencies table has
 * carried a `format` column from the start; nothing read it.
 *
 * Format 1 is what every amount looked like before, so a shop that never chose
 * sees no change.
 */

function nfShop(int $format = 1): Currency
{
    Currency::query()->update(['is_default' => false]);
    $eur = Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'format' => $format, 'rate' => 1, 'is_default' => true]);
    app()->forgetInstance('pnlcs.currency');

    return $eur;
}

test('a shop that never chose a format writes amounts exactly as before', function () {
    nfShop();

    expect(money_fmt(1234.5))->toBe('€1,234.50')
        ->and(money_fmt(0))->toBe('€0.00');
});

test('each format writes the decimal mark and the thousands its own way', function (int $format, string $expected) {
    nfShop($format);

    expect(money_fmt(1234567.891))->toBe($expected);
})->with([
    'comma thousands, point decimals' => [1, '€1,234,567.89'],
    'point thousands, comma decimals' => [2, '€1.234.567,89'],
    'space thousands, comma decimals' => [3, "€1\u{00A0}234\u{00A0}567,89"],
    'no thousands, point decimals' => [4, '€1234567.89'],
]);

test('a customer who sees lira sees the lira format, the shop currency keeps its own', function () {
    $eur = nfShop(1);
    $try = Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '₺', 'suffix' => '', 'format' => 2, 'rate' => 50, 'is_default' => false]);
    Setting::set(CustomerCurrency::SETTING, '1');
    CustomerCurrency::bind($try);

    expect(display_money_fmt(100))->toBe('₺5.000,00')
        ->and(money_fmt(100))->toBe('€100.00');
});

test('an invoice is written in the format of the currencies it is stamped in', function () {
    nfShop(1);
    Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '', 'suffix' => ' ₺', 'format' => 2, 'rate' => 50, 'is_default' => false]);

    $invoice = new Invoice(['source_currency' => 'EUR', 'billing_currency' => 'TRY', 'exchange_rate' => 50]);

    expect(invoice_money_fmt(1234.5, $invoice))->toBe('€1,234.50')
        ->and(billing_money_fmt(1234.5, $invoice))->toBe('61.725,00 ₺');
});

test('an unknown format falls back to the default one', function () {
    $eur = nfShop();
    $eur->format = 9;

    expect($eur->number(1234.5))->toBe('1,234.50');
});

test('staff set the format when adding or editing a currency, and only a known one', function () {
    nfShop();
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.currencies.store'), ['code' => 'TRY', 'prefix' => '₺', 'suffix' => '', 'format' => 2, 'rate' => 50])
        ->assertSessionHasNoErrors();

    $try = Currency::where('code', 'TRY')->firstOrFail();
    expect($try->format)->toBe(2);

    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.currencies.update', $try), ['code' => 'TRY', 'prefix' => '₺', 'suffix' => '', 'format' => 3, 'rate' => 50])
        ->assertSessionHasNoErrors();
    expect($try->fresh()->format)->toBe(3);

    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.currencies.update', $try), ['code' => 'TRY', 'prefix' => '₺', 'suffix' => '', 'format' => 7, 'rate' => 50])
        ->assertSessionHasErrors('format');
    expect($try->fresh()->format)->toBe(3);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.config.currencies'))
        ->assertOk()
        ->assertSee('1.234,56', false);
});

test('a currency saved without a format gets the default one', function () {
    nfShop();
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.currencies.store'), ['code' => 'GBP', 'prefix' => '£', 'rate' => 0.85])
        ->assertSessionHasNoErrors();

    expect(Currency::where('code', 'GBP')->value('format'))->toBe(1);
});
