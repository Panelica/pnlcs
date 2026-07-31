<?php

use App\Models\Currency;
use App\Models\Setting;

if (! function_exists('money_fmt')) {
    /**
     * An amount in the currency the shop sells in.
     *
     * Emails printed a dollar sign whatever the operator had configured, so a
     * customer buying in euros was billed in euros and told about dollars.
     */
    function money_fmt(float|int|string|null $amount): string
    {
        $value = number_format((float) $amount, 2);

        // Wrapped in an array: the container cannot resolve a bare null.
        if (! app()->bound('pnlcs.currency')) {
            try {
                app()->instance('pnlcs.currency', ['currency' => Currency::getDefault()]);
            } catch (Throwable) {
                app()->instance('pnlcs.currency', ['currency' => null]);
            }
        }

        $currency = app('pnlcs.currency')['currency'] ?? null;

        return $currency
            ? $currency->prefix.$value.$currency->suffix
            : '$'.$value;
    }
}
if (! function_exists('company_name')) {
    /**
     * What the business calls itself.
     *
     * The white-label name wins when the operator has set one — that is what
     * white-labelling means — then the company name from Settings, then the
     * application name. Subjects and bodies used to resolve this differently
     * and an email could carry both names at once.
     */
    function company_name(): string
    {
        if (app()->bound('pnlcs.company_name')) {
            return app('pnlcs.company_name');
        }

        try {
            $name = trim((string) Setting::get('whitelabel_company_name', ''))
                ?: trim((string) Setting::get('CompanyName', ''));
        } catch (Throwable) {
            $name = '';
        }

        $name = $name !== '' ? $name : (string) config('app.name', 'PNLCS');

        app()->instance('pnlcs.company_name', $name);

        return $name;
    }
}
if (! function_exists('date_fmt')) {
    /**
     * The date format the operator picked in Settings → General.
     *
     * Views pass this to Carbon's format() instead of hard-coding a pattern,
     * so changing the setting actually changes what customers see. The value
     * is resolved once per request: Setting::get() is a query every time.
     */
    function date_fmt(): string
    {
        if (app()->bound('pnlcs.date_format')) {
            return app('pnlcs.date_format');
        }

        $format = trim((string) Setting::get('DateFormat', ''));

        if ($format === '') {
            $format = 'd/m/Y';
        }

        app()->instance('pnlcs.date_format', $format);

        return $format;
    }
}

if (! function_exists('datetime_fmt')) {
    /**
     * The same date format with a 24-hour clock appended, for the places that
     * were showing a time as well.
     */
    function datetime_fmt(): string
    {
        return date_fmt().' H:i';
    }
}

if (! function_exists('display_tz')) {
    /**
     * The timezone the operator picked in Settings → General.
     *
     * Timestamps are stored in UTC and stay that way; this is only the clock
     * the panel shows them on. Resolved once per request, since Setting::get()
     * is a query every time.
     */
    function display_tz(): string
    {
        if (app()->bound('pnlcs.display_tz')) {
            return app('pnlcs.display_tz');
        }

        $fallback = (string) config('app.timezone', 'UTC');
        $tz = trim((string) Setting::get('Timezone', ''));

        if ($tz === '' || ! in_array($tz, timezone_identifiers_list(), true)) {
            $tz = $fallback;
        }

        app()->instance('pnlcs.display_tz', $tz);

        return $tz;
    }
}
