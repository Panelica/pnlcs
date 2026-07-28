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
