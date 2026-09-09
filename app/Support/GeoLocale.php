<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which country a visitor is coming from, and which language that implies.
 *
 * Deliberately built on headers rather than a GeoIP database: the reverse
 * proxies and CDNs in front of a hosting panel already resolve the country,
 * and shipping a MaxMind database that nobody updates would be worse than
 * reading a header that is right today.
 *
 * The rule the operator asked for: serve the visitor's own language when we
 * publish it, and **English when we do not** — never the site default. A
 * visitor from France getting Turkish is worse than getting English, because
 * English is the language they are most likely to have in common with us.
 */
class GeoLocale
{
    /**
     * Headers that carry a two-letter country, in the order we trust them.
     * Cloudflare first because it is the one most likely to be in front of us.
     */
    private const COUNTRY_HEADERS = [
        'CF-IPCountry',
        'X-Country-Code',
        'X-Geo-Country',
        'X-AppEngine-Country',
        'Fastly-Geo-Country',
    ];

    /** The language we would serve a country if we had the pack for it. */
    private const COUNTRY_LANGUAGE = [
        // Where Turkish is spoken
        'TR' => 'tr', 'CY' => 'tr', 'AZ' => 'tr',

        'DE' => 'de', 'AT' => 'de', 'CH' => 'de', 'LI' => 'de',
        'FR' => 'fr', 'BE' => 'fr', 'LU' => 'fr', 'MC' => 'fr', 'SN' => 'fr', 'CI' => 'fr',
        'ES' => 'es', 'MX' => 'es', 'AR' => 'es', 'CO' => 'es', 'CL' => 'es', 'PE' => 'es',
        'VE' => 'es', 'EC' => 'es', 'UY' => 'es', 'BO' => 'es', 'PY' => 'es', 'CR' => 'es',
        'PA' => 'es', 'DO' => 'es', 'GT' => 'es', 'HN' => 'es', 'SV' => 'es', 'NI' => 'es',
        'IT' => 'it', 'SM' => 'it', 'VA' => 'it',
        'PT' => 'pt-br', 'BR' => 'pt-br', 'AO' => 'pt-br', 'MZ' => 'pt-br',
        'NL' => 'nl',
        'RU' => 'ru', 'BY' => 'ru', 'KZ' => 'ru', 'KG' => 'ru', 'TJ' => 'ru',
        'UA' => 'uk',
        'PL' => 'pl',
        'CZ' => 'cs',
        'SK' => 'cs',
        'HU' => 'hu',
        'RO' => 'ro', 'MD' => 'ro',
        'HR' => 'hr', 'BA' => 'hr', 'RS' => 'hr', 'ME' => 'hr',
        'MK' => 'mk',
        'GR' => 'el',
        'SE' => 'sv',
        'DK' => 'da',
        'NO' => 'no',
        'FI' => 'fi',
        'EE' => 'et',
        'JP' => 'ja',
        'KR' => 'ko',
        'CN' => 'zh', 'TW' => 'zh', 'HK' => 'zh', 'SG' => 'zh',
        'IR' => 'fa', 'AF' => 'fa',
        'IL' => 'he',
        'SA' => 'ar', 'AE' => 'ar', 'EG' => 'ar', 'QA' => 'ar', 'KW' => 'ar',
        'BH' => 'ar', 'OM' => 'ar', 'JO' => 'ar', 'LB' => 'ar', 'IQ' => 'ar',
        'DZ' => 'ar', 'MA' => 'ar', 'TN' => 'ar', 'LY' => 'ar',
    ];

    /** The visitor's country as a two-letter code, or null. */
    public static function country(Request $request): ?string
    {
        foreach (self::COUNTRY_HEADERS as $header) {
            $value = strtoupper(trim((string) $request->header($header)));

            // Cloudflare answers 'XX' for addresses it cannot place, and 'T1'
            // for Tor. Neither is a country.
            if (preg_match('/^[A-Z]{2}$/', $value) && ! in_array($value, ['XX', 'T1'], true)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The language for this visitor, or null when we have nothing to go on.
     *
     * @param  array<int, string>  $activeLocales  The languages actually switched on.
     */
    public static function locale(Request $request, array $activeLocales): ?string
    {
        // The browser's stated preference wins over geography: a Turkish
        // speaker in Germany asked for Turkish, and a German colleague on the
        // same office network did not.
        $browser = $request->getPreferredLanguage();

        if ($browser) {
            $short = strtolower(substr($browser, 0, 2));

            if (in_array($short, $activeLocales, true)) {
                return $short;
            }
        }

        $country = self::country($request);

        // No country header at all — usually no CDN in front of us. Nothing to
        // reason from, so leave the decision to the site default.
        if ($country === null) {
            return null;
        }

        $wanted = self::COUNTRY_LANGUAGE[$country] ?? 'en';

        if (in_array($wanted, $activeLocales, true)) {
            return $wanted;
        }

        // We know where they are and we do not publish their language. English
        // rather than the site default: a visitor from France is far more
        // likely to read English than Turkish.
        return in_array('en', $activeLocales, true) ? 'en' : null;
    }
}
