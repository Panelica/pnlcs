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
if (! function_exists('payment_method_label')) {
    /**
     * A payment method key as the customer-facing label.
     *
     * The panel stores keys like 'banktransfer' or 'stripe' and a couple of
     * views printed ucfirst($method), which showed "Banktransfer" whatever
     * language the customer was in. The label comes from the translations now.
     */
    function payment_method_label(string $method): string
    {
        if ($method === '' || $method === 'none') {
            return $method;
        }

        $key = 'messages.payment_method.'.$method;

        if (Lang::has($key)) {
            return Lang::get($key);
        }

        return ucwords(str_replace('_', ' ', $method));
    }
}
if (! function_exists('invoice_status_label')) {
    /**
     * An invoice status key as the customer-facing label.
     *
     * The panel stores keys like 'unpaid' or 'overdue' and several views
     * printed ucfirst($status), which showed English whatever the language the
     * customer was in. The label comes from the common.status.* translations
     * now, so every screen agrees on the same word.
     */
    function invoice_status_label(?string $status): string
    {
        $status = strtolower((string) $status);

        if ($status === '') {
            return '';
        }

        $key = 'common.status.'.$status;

        if (Lang::has($key)) {
            return Lang::get($key);
        }

        return ucfirst(str_replace('_', ' ', $status));
    }
}
if (! function_exists('currency_symbol')) {
    /**
     * The sign in front of an amount in the currency the shop sells in.
     *
     * For the few places that print a rate rather than an amount - a price per
     * megabyte, say - where rounding to two decimals would misstate it.
     */
    function currency_symbol(): string
    {
        if (! app()->bound('pnlcs.currency')) {
            try {
                app()->instance('pnlcs.currency', ['currency' => Currency::getDefault()]);
            } catch (Throwable) {
                app()->instance('pnlcs.currency', ['currency' => null]);
            }
        }

        return (string) (app('pnlcs.currency')['currency']->prefix ?? '$');
    }
}if (! function_exists('shop_currency_code')) {
    /**
     * The three-letter code of the currency the shop sells in.
     *
     * Everything is priced, invoiced and charged in this one currency; the
     * gateways used to be told nothing and each fell back to a different
     * default of its own.
     */
    function shop_currency_code(): string
    {
        if (! app()->bound('pnlcs.currency')) {
            try {
                app()->instance('pnlcs.currency', ['currency' => Currency::getDefault()]);
            } catch (Throwable) {
                app()->instance('pnlcs.currency', ['currency' => null]);
            }
        }

        return strtoupper((string) (app('pnlcs.currency')['currency']->code ?? 'USD'));
    }
}if (! function_exists('company_name')) {
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

if (! function_exists('branding_removed')) {
    /**
     * Whether the operator asked for the product's own name to be taken off
     * the pages their customers see.
     *
     * Settings -> Appearance has offered this switch all along; nothing read
     * it, so a reseller who turned it on still handed their customers pages
     * headed PNLCS.
     */
    function branding_removed(): bool
    {
        try {
            return (string) Setting::get('whitelabel_remove_branding', '0') === '1';
        } catch (Throwable) {
            return false;
        }
    }
}

if (! function_exists('csv_cell')) {
    /**
     * A value as text, not as something a spreadsheet will run.
     *
     * A cell beginning with =, +, - or @ is a formula to Excel, Numbers and
     * LibreOffice, and it runs when the file is opened. The fields in these
     * exports are the ones customers fill in themselves, and the person who
     * opens the file is the operator, so the leading character is quoted and
     * the value stays readable.
     */
    function csv_cell(mixed $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return $value;
        }

        return str_starts_with($value, '=')
            || str_starts_with($value, '+')
            || str_starts_with($value, '-')
            || str_starts_with($value, '@')
            || str_starts_with($value, "\t")
            || str_starts_with($value, "\r")
                ? "'".$value
                : $value;
    }
}

if (! function_exists('domain_money_fmt')) {
    /**
     * A domain price shown in US dollars.
     *
     * Domains are bought from the registrar in USD, so the store lists them in
     * USD even when the shop bills in its own currency. The stored amount is in
     * the shop currency; this converts it back at the current rate. Nothing
     * about what the customer is charged changes - only what is printed.
     */
    function domain_money_fmt(float|int|string|null $amount): string
    {
        static $usdRate = null;

        if ($usdRate === null) {
            try {
                $default = Currency::getDefault();
                if ($default && strtoupper($default->code) === 'USD') {
                    $usdRate = 1.0;
                } else {
                    $usd = Currency::where('code', 'USD')->first();
                    $usdRate = $usd && (float) $usd->rate > 0 ? (float) $usd->rate : 0.0;
                }
            } catch (Throwable) {
                $usdRate = 0.0;
            }
        }

        // No USD currency configured: fall back to the shop currency rather
        // than printing a dollar sign over an amount that is not dollars.
        if ($usdRate <= 0) {
            return money_fmt($amount);
        }

        return '$'.number_format((float) $amount * $usdRate, 2);
    }
}

if (! function_exists('currency_code_default')) {
    /** The code of the currency the shop prices in. */
    function currency_code_default(): string
    {
        try {
            return strtoupper(Currency::getDefault()->code ?? 'USD');
        } catch (Throwable) {
            return 'USD';
        }
    }
}

if (! function_exists('kb_enabled')) {
    /**
     * Whether the knowledge base is shown to customers.
     *
     * The menu entry appears in nine places - top menu, footer, client panel,
     * mobile menu, contact page. Commenting nine out and later finding nine
     * to put back is how one of them gets missed; they all read this switch.
     *
     * On by default: an installation that never wrote the setting keeps
     * showing its knowledge base.
     */
    function kb_enabled(): bool
    {
        try {
            return (string) Setting::get('KnowledgeBaseEnabled', '1') !== '0';
        } catch (\Throwable) {
            return true;
        }
    }
}

if (! function_exists('invoice_money_fmt')) {
    /**
     * An amount in the currency the invoice itself was written in.
     *
     * Never the shop's current currency: an invoice raised in dollars stays a
     * dollar invoice after the shop moves to lira, and the reverse. Only
     * documents that predate the stamp fall back to the shop currency.
     */
    function invoice_money_fmt(float|int|string|null $amount, $invoice = null): string
    {
        static $cache = [];

        $code = $invoice->source_currency ?? null;

        if (! $code) {
            return money_fmt($amount);
        }

        $code = strtoupper($code);

        if (! array_key_exists($code, $cache)) {
            try {
                $cache[$code] = Currency::where('code', $code)->first();
            } catch (Throwable) {
                $cache[$code] = null;
            }
        }

        $currency = $cache[$code];
        $value = number_format((float) $amount, 2);

        if (! $currency) {
            return $value.' '.$code;
        }

        $prefix = $currency->prefix ?? '';
        $suffix = $currency->suffix ?? '';

        if ($prefix === '' && $suffix === '') {
            $suffix = ' '.$code;
        }

        return $prefix.$value.$suffix;
    }
}

if (! function_exists('billing_money_fmt')) {
    /**
     * An amount as the customer is actually billed for it.
     *
     * The shop prices in one currency and bills in another; the invoice froze
     * the rate when it was raised, so the figure printed on a document does
     * not drift as the rate moves.
     */
    function billing_money_fmt(float|int|string|null $amount, $invoice = null): string
    {
        if (! $invoice || empty($invoice->billing_currency) || ! (float) $invoice->exchange_rate) {
            return money_fmt($amount);
        }

        $converted = round((float) $amount * (float) $invoice->exchange_rate, 2);

        $currency = Currency::where('code', $invoice->billing_currency)->first();
        $prefix = $currency->prefix ?? '';
        $suffix = $currency->suffix ?? '';

        if ($prefix === '' && $suffix === '') {
            $suffix = ' '.$invoice->billing_currency;
        }

        return $prefix.number_format($converted, 2).$suffix;
    }
}

if (! function_exists('payment_ref')) {
    /**
     * The short reference a customer writes in a bank transfer description.
     *
     * The invoice number (INV-202608-000002) is the document's official
     * number and stays so, but the customer types it into a bank's
     * description box by hand: nineteen characters, two dashes, six digits of
     * zeroes. One wrong digit and the payment cannot be matched. The reference
     * is the invoice id behind a short prefix instead - short, readable over
     * the phone, hard to mistype. The prefix is a setting so that a host can
     * keep the code its customers already know.
     */
    function payment_ref($invoice = null): string
    {
        if (! $invoice) {
            return '';
        }

        try {
            $prefix = strtoupper(trim((string) Setting::get('PaymentReferencePrefix', 'INV')));
        } catch (Throwable) {
            $prefix = 'INV';
        }

        return ($prefix !== '' ? $prefix : 'INV').((int) ($invoice->id ?? 0));
    }
}

if (! function_exists('has_billing_conversion')) {
    /** Whether the invoice carries a separate billing-currency stamp. */
    function has_billing_conversion($invoice = null): bool
    {
        if (! $invoice || empty($invoice->billing_currency) || ! (float) $invoice->exchange_rate) {
            return false;
        }

        return strtoupper((string) $invoice->billing_currency)
            !== strtoupper((string) ($invoice->source_currency ?? currency_code_default()));
    }
}

if (! function_exists('billing_amount')) {
    /** The raw amount in the billing currency - for writing into input fields. */
    function billing_amount(float|int|string|null $amount, $invoice = null): float
    {
        if (! has_billing_conversion($invoice)) {
            return round((float) $amount, 2);
        }

        return round((float) $amount * (float) $invoice->exchange_rate, 2);
    }
}

if (! function_exists('dual_money_fmt')) {
    /**
     * The amount due in both currencies: billing currency first, then the
     * shop currency in brackets.
     *
     * The shop prices in one currency and the customer pays in another.
     * Telling them to transfer "6.60 USD" is an instruction with no
     * counterpart at their bank; the figure they will actually pay in is the
     * one that leads. The shop figure stays because the invoice lines and any
     * refund are worked out in it.
     */
    function dual_money_fmt(float|int|string|null $amount, $invoice = null): string
    {
        if (! has_billing_conversion($invoice)) {
            return invoice_money_fmt($amount, $invoice);
        }

        return billing_money_fmt($amount, $invoice).' ('.invoice_money_fmt($amount, $invoice).')';
    }
}

if (! function_exists('billing_rate_note')) {
    /**
     * The sentence that tells the customer which rate their bill was struck at.
     */
    function billing_rate_note($invoice): ?string
    {
        if (! $invoice || empty($invoice->billing_currency) || ! (float) $invoice->exchange_rate) {
            return null;
        }

        $shop = Currency::getDefault();
        $from = strtoupper($invoice->source_currency ?: ($shop->code ?? 'USD'));
        $to = strtoupper($invoice->billing_currency);
        $rate = number_format((float) $invoice->exchange_rate, 4);

        // Without a named source the sentence is just an assertion; with one
        // the customer can open the bulletin and check the number.
        if (! empty($invoice->exchange_rate_source)) {
            $kinds = [
                'ForexSelling' => __('pdf.rate_kind_selling'),
                'ForexBuying' => __('pdf.rate_kind_buying'),
            ];

            return __('pdf.rate_note_official', [
                'source' => $invoice->exchange_rate_source,
                'date' => $invoice->exchange_rate_date ?: '',
                'kind' => $kinds[$invoice->exchange_rate_kind] ?? __('pdf.rate_kind_selling'),
                'from' => $from,
                'to' => $to,
                'rate' => $rate,
                'ref' => $invoice->exchange_rate_ref ? ' ('.__('pdf.rate_bulletin').' '.$invoice->exchange_rate_ref.')' : '',
            ]);
        }

        return __('pdf.rate_note', ['from' => $from, 'to' => $to, 'rate' => $rate]);
    }
}

if (! function_exists('funds_round_preset')) {
    /**
     * A top-up preset converted at the day's rate, rounded to a figure a
     * person would actually type: 5 x 40.13 is offered as 200, not 200.65.
     */
    function funds_round_preset(float $amount): float
    {
        if ($amount < 10) {
            return max(1.0, round($amount));
        }

        $magnitude = 10 ** (floor(log10($amount)) - 1);

        return (float) (round($amount / $magnitude) * $magnitude);
    }
}
