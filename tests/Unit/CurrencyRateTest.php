<?php

use App\Models\Currency;

beforeEach(function () {
    Currency::query()->delete();
});

it('returns 1.0 when the source is the shop currency', function () {
    Currency::create(['code' => 'PLN', 'name' => 'PLN', 'prefix' => '', 'suffix' => ' zł', 'rate' => 1.0, 'is_default' => true]);

    expect(Currency::rateToShop('PLN'))->toBe(1.0)
        ->and(Currency::toShop(5.0, 'PLN'))->toBe(5.0);
});

it('converts from the stored rate, in shop units per foreign unit', function () {
    Currency::create(['code' => 'PLN', 'name' => 'PLN', 'prefix' => '', 'suffix' => ' zł', 'rate' => 1.0, 'is_default' => true]);
    // 0.25 EUR for one PLN: one EUR is worth 4 PLN.
    Currency::create(['code' => 'EUR', 'name' => 'EUR', 'prefix' => '', 'suffix' => ' €', 'rate' => 0.25, 'is_default' => false]);

    expect(Currency::rateToShop('EUR'))->toBe(4.0)
        ->and(Currency::toShop(10.0, 'EUR'))->toBe(40.0);
});
