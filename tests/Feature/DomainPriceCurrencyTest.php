<?php

use App\Models\Currency;

/*
 * Domain prices used to be printed in US dollars whatever the shop billed in,
 * so the search page could show a different currency (and figure) from the
 * one actually charged. They now follow the shop currency like every other
 * price.
 */

test('domain prices are shown in the shop currency, not dollars', function () {
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => 'PLN'], [
        'prefix' => '', 'suffix' => ' zł', 'format' => 1, 'rate' => 1, 'is_default' => true,
    ]);
    app()->forgetInstance('pnlcs.currency');

    expect(domain_money_fmt(50))->toBe('50.00 zł')
        ->and(domain_money_fmt(50))->not->toContain('$');
});
