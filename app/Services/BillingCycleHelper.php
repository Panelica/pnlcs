<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Central mapping between a service billing cycle and (a) the matching column
 * on the `pricing` table, (b) its nominal length in days (used for proration),
 * and (c) how to advance a date by one cycle. Keeping this in one place avoids
 * drift between invoice generation, renewal date advancement and upgrade
 * proration.
 */
class BillingCycleHelper
{
    /**
     * The longest term that may be named by its months.
     *
     * Panelica Central sells a licence for 1 to 12 months and nothing here
     * sells a term it cannot buy, so "13months" is not a cycle at all.
     */
    public const MAX_TERM_MONTHS = 12;

    /** How many months each named cycle covers. */
    private const NAMED = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
        'biennially' => 24,
        'triennially' => 36,
    ];

    /** The named cycle for a length in months, where there is one. */
    private const NAME_FOR_MONTHS = [1 => 'monthly', 3 => 'quarterly', 6 => 'semiannually', 12 => 'annually', 24 => 'biennially', 36 => 'triennially'];

    private static function key(string $cycle): string
    {
        $c = strtolower(trim(str_replace([' ', '_'], '', $cycle)));

        return $c === 'semi-annually' ? 'semiannually' : $c;
    }

    /**
     * How many months a cycle covers, or null when it is not a cycle at all.
     *
     * Either one of the six names, or a term of months written as its length -
     * "7months" - for the terms no name covers. Central sells any term of 1 to
     * 12 months; a product's price for such a term lives in `product_terms`
     * rather than in a column of its own.
     */
    public static function months(string $cycle): ?int
    {
        $key = self::key($cycle);

        if (isset(self::NAMED[$key])) {
            return self::NAMED[$key];
        }

        if (preg_match('/^([0-9]{1,2})months?$/', $key, $m)) {
            $months = (int) $m[1];

            return $months >= 1 && $months <= self::MAX_TERM_MONTHS ? $months : null;
        }

        return null;
    }

    /**
     * The cycle to store for a term of so many months: the name where the term
     * has one, so a monthly product stays "monthly" and nothing has to learn a
     * second spelling for a period it already handles.
     */
    public static function cycleForMonths(int $months): string
    {
        return self::NAME_FOR_MONTHS[$months] ?? $months.'months';
    }

    /**
     * The validation rule for a billing cycle: one of the six names, or a term
     * of months Central sells. Both the cart and the admin order screen use it,
     * so neither can drift from what months() accepts.
     */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || self::months($value) === null) {
                $fail(__('client.cart.cycle_unavailable'));
            }
        };
    }

    /** Column on the `pricing` table for this cycle, or null if unknown. */
    public static function pricingColumn(string $cycle): ?string
    {
        return match (self::key($cycle)) {
            'monthly'      => 'monthly',
            'quarterly'    => 'quarterly',
            'semiannually' => 'semiannually',
            'annually'     => 'annually',
            'biennially'   => 'biennially',
            'triennially'  => 'triennially',
            default        => null,
        };
    }

    /** Nominal length of the cycle in days (for proration factors). */
    public static function cycleDays(string $cycle): int
    {
        return match (self::key($cycle)) {
            'monthly'      => 30,
            'quarterly'    => 91,
            'semiannually' => 182,
            'annually'     => 365,
            'biennially'   => 730,
            'triennially'  => 1095,
            // A term of months is as long as that many months of thirty days,
            // which is how the named cycles under a year are counted here.
            default        => 30 * (self::months($cycle) ?? 1),
        };
    }

    /** Advance a date by exactly one billing cycle (non-mutating).
     *
     * NoOverflow throughout: plain addMonth turns 31 January into 3 March, so
     * a customer billed on a month-end had February skipped and their
     * anniversary crept forward for good. Clamping to the last day of the
     * target month keeps the anniversary where it was sold. */
    public static function advance(Carbon $from, string $cycle): Carbon
    {
        $d = $from->copy();
        return match (self::key($cycle)) {
            'monthly'      => $d->addMonthsNoOverflow(1),
            'quarterly'    => $d->addMonthsNoOverflow(3),
            'semiannually' => $d->addMonthsNoOverflow(6),
            'annually'     => $d->addYearsNoOverflow(1),
            'biennially'   => $d->addYearsNoOverflow(2),
            'triennially'  => $d->addYearsNoOverflow(3),
            // A term of months, and anything unrecognised as one month, which
            // is what this did before terms existed.
            default        => $d->addMonthsNoOverflow(self::months($cycle) ?? 1),
        };
    }
}
