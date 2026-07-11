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
    private static function key(string $cycle): string
    {
        $c = strtolower(trim($cycle));
        return $c === 'semi-annually' ? 'semiannually' : $c;
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
            default        => 30,
        };
    }

    /** Advance a date by exactly one billing cycle (non-mutating). */
    public static function advance(Carbon $from, string $cycle): Carbon
    {
        $d = $from->copy();
        return match (self::key($cycle)) {
            'monthly'      => $d->addMonth(),
            'quarterly'    => $d->addMonths(3),
            'semiannually' => $d->addMonths(6),
            'annually'     => $d->addYear(),
            'biennially'   => $d->addYears(2),
            'triennially'  => $d->addYears(3),
            default        => $d->addMonth(),
        };
    }
}
