<?php

namespace App\Support;

/**
 * The currency a country uses, by ISO codes (3166-1 alpha-2 to 4217).
 *
 * A table rather than the intl extension: PNLCS does not require intl, and
 * what ICU answers depends on the version a server happens to carry. Only
 * used to guess which currency a visitor starts in (CustomerCurrency), so a
 * country that is missing here simply starts in the shop currency.
 */
class CountryCurrency
{
    /** @var array<string, string> currency => the countries that use it, space-separated */
    private const COUNTRIES = [
        'EUR' => 'AD AT AX BE BG BL CY DE EE ES FI FR GF GP GR HR IE IT LT LU LV MC ME MF MQ MT NL PM PT RE SI SK SM TF VA XK YT',
        'USD' => 'US AS BQ EC FM GU IO MH MP PA PR PW SV TC TL UM VG VI',
        'GBP' => 'GB GG IM JE',
        'XOF' => 'BF BJ CI GW ML NE SN TG',
        'XAF' => 'CF CG CM GA GQ TD',
        'XCD' => 'AG AI DM GD KN LC MS VC',
        'XPF' => 'NC PF WF',
        'AUD' => 'AU CC CX HM KI NF NR TV',
        'NZD' => 'NZ CK NU PN TK',
        'DKK' => 'DK FO GL',
        'NOK' => 'NO BV SJ',
        'CHF' => 'CH LI',
        'MAD' => 'MA EH',
        'ILS' => 'IL PS',
        // The Americas
        'CAD' => 'CA', 'MXN' => 'MX', 'BRL' => 'BR', 'ARS' => 'AR', 'CLP' => 'CL', 'COP' => 'CO', 'PEN' => 'PE',
        'UYU' => 'UY', 'PYG' => 'PY', 'BOB' => 'BO', 'VES' => 'VE', 'CRC' => 'CR', 'GTQ' => 'GT', 'HNL' => 'HN',
        'NIO' => 'NI', 'DOP' => 'DO', 'JMD' => 'JM', 'TTD' => 'TT', 'BSD' => 'BS', 'BBD' => 'BB', 'BZD' => 'BZ',
        'HTG' => 'HT', 'CUP' => 'CU', 'GYD' => 'GY', 'SRD' => 'SR', 'AWG' => 'AW', 'BMD' => 'BM', 'KYD' => 'KY',
        // Europe and Central Asia
        'TRY' => 'TR', 'PLN' => 'PL', 'CZK' => 'CZ', 'HUF' => 'HU', 'RON' => 'RO', 'SEK' => 'SE', 'ISK' => 'IS',
        'UAH' => 'UA', 'RUB' => 'RU', 'BYN' => 'BY', 'MDL' => 'MD', 'RSD' => 'RS', 'BAM' => 'BA', 'MKD' => 'MK',
        'ALL' => 'AL', 'GEL' => 'GE', 'AMD' => 'AM', 'AZN' => 'AZ', 'KZT' => 'KZ', 'UZS' => 'UZ', 'KGS' => 'KG',
        'TJS' => 'TJ', 'TMT' => 'TM', 'GIP' => 'GI',
        // Asia and the Pacific
        'JPY' => 'JP', 'CNY' => 'CN', 'HKD' => 'HK', 'TWD' => 'TW', 'KRW' => 'KR', 'SGD' => 'SG', 'MYR' => 'MY',
        'THB' => 'TH', 'IDR' => 'ID', 'PHP' => 'PH', 'VND' => 'VN', 'INR' => 'IN', 'PKR' => 'PK', 'BDT' => 'BD',
        'LKR' => 'LK', 'NPR' => 'NP', 'MMK' => 'MM', 'KHR' => 'KH', 'LAK' => 'LA', 'MNT' => 'MN', 'MOP' => 'MO',
        'BND' => 'BN', 'MVR' => 'MV', 'AFN' => 'AF', 'BTN' => 'BT', 'FJD' => 'FJ', 'PGK' => 'PG', 'WST' => 'WS',
        'TOP' => 'TO', 'VUV' => 'VU', 'SBD' => 'SB',
        // The Middle East
        'SAR' => 'SA', 'AED' => 'AE', 'QAR' => 'QA', 'KWD' => 'KW', 'BHD' => 'BH', 'OMR' => 'OM', 'JOD' => 'JO',
        'LBP' => 'LB', 'IQD' => 'IQ', 'IRR' => 'IR', 'SYP' => 'SY', 'YER' => 'YE',
        // Africa
        'EGP' => 'EG', 'ZAR' => 'ZA', 'NGN' => 'NG', 'GHS' => 'GH', 'KES' => 'KE', 'TZS' => 'TZ', 'UGX' => 'UG',
        'RWF' => 'RW', 'ETB' => 'ET', 'DZD' => 'DZ', 'TND' => 'TN', 'LYD' => 'LY', 'SDG' => 'SD', 'AOA' => 'AO',
        'MZN' => 'MZ', 'ZMW' => 'ZM', 'BWP' => 'BW', 'NAD' => 'NA', 'MUR' => 'MU', 'MGA' => 'MG', 'MWK' => 'MW',
        'CDF' => 'CD', 'GMD' => 'GM', 'GNF' => 'GN', 'SLE' => 'SL', 'LRD' => 'LR', 'CVE' => 'CV', 'SCR' => 'SC',
        'DJF' => 'DJ', 'SOS' => 'SO', 'ERN' => 'ER', 'BIF' => 'BI', 'KMF' => 'KM', 'LSL' => 'LS', 'SZL' => 'SZ',
        'SSP' => 'SS', 'MRU' => 'MR', 'STN' => 'ST',
    ];

    /** The three-letter currency code for a two-letter country code, or null. */
    public static function for(?string $country): ?string
    {
        $country = strtoupper(trim((string) $country));

        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            return null;
        }

        static $byCountry = null;

        if ($byCountry === null) {
            $byCountry = [];

            foreach (self::COUNTRIES as $currency => $countries) {
                foreach (explode(' ', $countries) as $code) {
                    $byCountry[$code] = $currency;
                }
            }
        }

        return $byCountry[$country] ?? null;
    }
}
