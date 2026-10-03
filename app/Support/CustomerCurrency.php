<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Http\Request;
use Throwable;

/**
 * The currency a customer sees prices in and is invoiced in.
 *
 * The books stay in the shop's default currency: every price, invoice line,
 * credit balance, refund and charge is worked out in it, exactly as before.
 * A customer who chose another currency is shown prices converted at the
 * daily rate, and each of their invoices carries that currency with the rate
 * frozen the day it was raised - the same stamp the shop-wide "bill
 * customers in" setting writes (Invoice::booted()).
 *
 * Off unless the operator switches it on (Setup > Currencies), and only
 * meaningful with a second currency defined.
 *
 * Who chooses: a visitor, for the session, from the top bar; the choice is
 * kept on the account they open. After that the account's currency is
 * changed by staff, on the client's profile - a link a customer can be sent
 * to must not be able to change what their next invoice is billed in.
 */
class CustomerCurrency
{
    /** The setting that switches the feature on. */
    public const SETTING = 'CustomerCurrencyChoice';

    /** Where a visitor's choice is kept for the session. */
    public const SESSION_KEY = 'display_currency_id';

    /** Container key for the currency the current request shows prices in. */
    public const BINDING = 'pnlcs.display_currency';

    public static function enabled(): bool
    {
        try {
            return (string) Setting::get(self::SETTING, '0') === '1'
                && Currency::count() > 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether a currency can be used for a customer: it exists and has a rate
     * to convert with. A currency without a rate would show the shop figure
     * under another currency's sign.
     */
    public static function usable(?Currency $currency): bool
    {
        if (! $currency) {
            return false;
        }

        $shop = Currency::getDefault();

        if (! $shop) {
            return false;
        }

        return $currency->id === $shop->id || (float) $currency->rate > 0;
    }

    /**
     * How many units of $currency one unit of the shop currency is worth.
     *
     * Rates are stored against the default currency, whose own rate is 1 by
     * definition (pnlcs:currency-update keeps it there). Divided anyway, so a
     * default whose rate an operator typed by hand still converts correctly.
     */
    public static function rateOf(Currency $currency): float
    {
        $shop = Currency::getDefault();

        if (! $shop || $currency->id === $shop->id) {
            return 1.0;
        }

        $base = (float) $shop->rate > 0 ? (float) $shop->rate : 1.0;

        return (float) $currency->rate / $base;
    }

    /**
     * The currency a client's invoices are billed in, when it is their own
     * choice and not the shop's: null when the feature is off, the client has
     * none, or it is the shop currency - the shop-wide rules apply then.
     */
    public static function forClient(?Client $client): ?Currency
    {
        if (! $client || ! $client->currency_id || ! self::enabled()) {
            return null;
        }

        $currency = Currency::find($client->currency_id);
        $shop = Currency::getDefault();

        if (! $currency || ! $shop || $currency->id === $shop->id || ! self::usable($currency)) {
            return null;
        }

        return $currency;
    }

    /**
     * The currency a new account starts in: the one the visitor picked in the
     * top bar during this session, if the feature is on and it still exists.
     */
    public static function chosenForNewAccount(Request $request): ?int
    {
        if (! self::enabled() || ! $request->hasSession()) {
            return null;
        }

        $currency = Currency::find((int) $request->session()->get(self::SESSION_KEY));

        return self::usable($currency) ? $currency->id : null;
    }

    /**
     * The currency the current visitor sees prices in.
     *
     * A logged-in customer: their account's currency. A visitor: the one
     * picked this session. Anyone else, or with the feature off: the shop's.
     */
    public static function resolve(Request $request, ?Client $client): ?Currency
    {
        $shop = Currency::getDefault();

        if (! self::enabled()) {
            return $shop;
        }

        if ($client) {
            return ($client->currency_id ? self::forClient($client) : null) ?? $shop;
        }

        if ($request->hasSession()) {
            $chosen = Currency::find((int) $request->session()->get(self::SESSION_KEY));

            if (self::usable($chosen)) {
                return $chosen;
            }
        }

        return $shop;
    }

    /**
     * Fix the currency this request shows prices in, once: every price on a
     * page reads it, and working it out again for each one would be a query
     * per price.
     */
    public static function bind(?Currency $currency): void
    {
        $shop = Currency::getDefault();
        $converting = $currency && $shop && $currency->id !== $shop->id;

        app()->instance(self::BINDING, [
            'currency' => $currency ?? $shop,
            'rate' => $converting ? self::rateOf($currency) : 1.0,
            'converting' => $converting,
        ]);
    }

    /**
     * The currency bound for this request, or the shop's outside one (the
     * scheduler, a queued mail, the admin area).
     */
    public static function current(): ?Currency
    {
        if (app()->bound(self::BINDING)) {
            return app(self::BINDING)['currency'] ?? null;
        }

        try {
            return Currency::getDefault();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A shop-currency amount in the currency this request shows prices in.
     * Outside a bound request nothing is converted.
     */
    public static function convert(float|int|string|null $amount): float
    {
        $rate = app()->bound(self::BINDING) ? (float) (app(self::BINDING)['rate'] ?? 1.0) : 1.0;

        return round((float) $amount * $rate, 2);
    }

    /** Whether this request shows prices in a currency other than the shop's. */
    public static function converting(): bool
    {
        return app()->bound(self::BINDING) && (bool) (app(self::BINDING)['converting'] ?? false);
    }
}
