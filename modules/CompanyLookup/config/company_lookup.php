<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company lookup (GUS REGON / MF Biała Lista / CEIDG) defaults.
    |--------------------------------------------------------------------------
    |
    | These are the built-in defaults. Operator overrides (including the GUS
    | and CEIDG API keys) live in the `addon_settings` table and are managed
    | from the admin panel — never in .env and never committed to the repo.
    |
    */

    'gus' => [
        'key' => null,
        'endpoint' => 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIR1.asmx',
    ],

    'mf' => [
        'endpoint' => 'https://wl-api.mf.gov.pl/api',
    ],

    'ceidg' => [
        'key' => null,
        'endpoint' => 'https://dane.biznes.gov.pl/api/ceidg/v3',
    ],

    'openbris' => [
        'key' => null,
        'endpoint' => 'https://api.openbris.eu',
    ],

    /*
     * How long a successful result is cached, in seconds. Registry data must
     * not be cached forever; the default is one day.
     */
    'cache_ttl' => 86400,

    'http' => [
        'connect_timeout' => 5,
        'request_timeout' => 10,
    ],
];
