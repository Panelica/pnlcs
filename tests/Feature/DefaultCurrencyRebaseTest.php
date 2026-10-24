<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;

/**
 * Exchange rates are stored against the default currency, whose rate is 1.
 * "Make default" moved only the flag: the new default kept its old rate and
 * every other rate stayed relative to the previous default until the next rate
 * update, which runs only when automatic updates are on.
 */
function rebaseAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

it('restates every rate against the new default', function () {
    Currency::query()->update(['is_default' => false]);
    $eur = Currency::factory()->create(['code' => 'EUX', 'rate' => 1, 'is_default' => true]);
    $try = Currency::factory()->create(['code' => 'TRX', 'rate' => 40, 'is_default' => false]);
    $usd = Currency::factory()->create(['code' => 'USX', 'rate' => 1.25, 'is_default' => false]);

    $this->actingAs(rebaseAdmin(), 'admin')
        ->post(route('admin.config.currencies.default', $try))->assertRedirect();

    expect((float) $try->fresh()->rate)->toBe(1.0)
        ->and($try->fresh()->is_default)->toBeTruthy()
        ->and((float) $eur->fresh()->rate)->toBe(0.025)
        ->and((float) $usd->fresh()->rate)->toBe(0.03125)
        ->and($eur->fresh()->is_default)->toBeFalsy();
});

it('changes nothing when the currency already is the default', function () {
    Currency::query()->update(['is_default' => false]);
    $eur = Currency::factory()->create(['code' => 'EUY', 'rate' => 1, 'is_default' => true]);
    $try = Currency::factory()->create(['code' => 'TRY', 'rate' => 40, 'is_default' => false]);

    $this->actingAs(rebaseAdmin(), 'admin')
        ->post(route('admin.config.currencies.default', $eur))->assertRedirect();

    expect((float) $try->fresh()->rate)->toBe(40.0)
        ->and((float) $eur->fresh()->rate)->toBe(1.0);
});

/*
 * The TCMB bulletin details (pnlcs:currency-update) describe the lira rate the
 * bulletin gave against the default currency. Once the default changes the
 * stored lira rate is restated from the others and no longer the bulletin's
 * figure, so a lira invoice would print a bulletin its rate did not come from.
 * The rate update clears them in the same case.
 */
function rebaseProvenance(): void
{
    foreach (['ExchangeRateSource' => 'TCMB', 'ExchangeRateKind' => 'ForexSelling', 'ExchangeRateDate' => '2031-03-10', 'ExchangeRateBulletin' => '2031/48'] as $key => $value) {
        \App\Models\Setting::set($key, $value);
    }
}

it('forgets the bulletin a restated lira rate no longer comes from', function () {
    Currency::query()->update(['is_default' => false]);
    Currency::factory()->create(['code' => 'USZ', 'rate' => 1, 'is_default' => true]);
    $eur = Currency::factory()->create(['code' => 'EUZ', 'rate' => 0.9, 'is_default' => false]);
    rebaseProvenance();

    $this->actingAs(rebaseAdmin(), 'admin')
        ->post(route('admin.config.currencies.default', $eur))->assertRedirect();

    foreach (['ExchangeRateSource', 'ExchangeRateKind', 'ExchangeRateDate', 'ExchangeRateBulletin'] as $key) {
        expect((string) \App\Models\Setting::get($key))->toBe('', $key);
    }
});

it('keeps the bulletin when the default stays where it is', function () {
    Currency::query()->update(['is_default' => false]);
    $usd = Currency::factory()->create(['code' => 'USW', 'rate' => 1, 'is_default' => true]);
    rebaseProvenance();

    $this->actingAs(rebaseAdmin(), 'admin')
        ->post(route('admin.config.currencies.default', $usd))->assertRedirect();

    expect(\App\Models\Setting::get('ExchangeRateBulletin'))->toBe('2031/48');
});
