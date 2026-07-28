<?php

use App\Models\Setting;

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
