<?php

use App\Services\BillingCycleHelper;
use Carbon\Carbon;

/**
 * Carbon's plain addMonth overflows: 31 January plus one month is 3 March, so
 * a customer billed on a month-end had February skipped entirely and their
 * anniversary crept forward for good - 31 days of service sold as one month,
 * on every cycle that lands on a short month. Advancing must clamp to the
 * last day of the target month instead.
 */
it('clamps a month-end monthly renewal instead of overflowing past February', function () {
    expect(BillingCycleHelper::advance(Carbon::parse('2026-01-31'), 'monthly')->toDateString())
        ->toBe('2026-02-28');
});

it('clamps quarterly and semi-annual advances the same way', function () {
    expect(BillingCycleHelper::advance(Carbon::parse('2026-01-31'), 'quarterly')->toDateString())
        ->toBe('2026-04-30')
        ->and(BillingCycleHelper::advance(Carbon::parse('2026-08-31'), 'semi-annually')->toDateString())
        ->toBe('2027-02-28');
});

it('clamps a leap-day annual renewal to 28 February', function () {
    expect(BillingCycleHelper::advance(Carbon::parse('2028-02-29'), 'annually')->toDateString())
        ->toBe('2029-02-28');
});

it('leaves ordinary dates exactly one cycle ahead', function () {
    expect(BillingCycleHelper::advance(Carbon::parse('2026-03-15'), 'monthly')->toDateString())
        ->toBe('2026-04-15')
        ->and(BillingCycleHelper::advance(Carbon::parse('2026-03-15'), 'annually')->toDateString())
        ->toBe('2027-03-15');
});
