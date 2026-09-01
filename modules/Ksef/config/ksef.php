<?php

return [
    // Operator's NIP (TIN) — the taxpayer whose invoices are sent.
    'nip' => null,

    // KSeF 2.0 environment: 'integration', 'demo' or 'prod'.
    //   - integration: anonymised test data, no legal effect.
    //   - demo:        pre-production, real auth (MCU), no legal effect.
    //   - prod:        production, real invoices.
    'environment' => 'integration',

    // KSeF 2.0 API endpoints (official hosts from the OpenAPI contract).
    'endpoints' => [
        'integration' => 'https://api-test.ksef.mf.gov.pl/v2',
        'demo' => 'https://api-demo.ksef.mf.gov.pl/v2',
        'prod' => 'https://api.ksef.mf.gov.pl/v2',
    ],

    // KSeF 2.0 authenticates with a qualified seal / signature (XAdES) using
    // the MCU certificate. The private key and certificate are pasted into the
    // addon settings (stored encrypted).
    'private_key' => null,
    'private_key_passphrase' => null,
    'certificate' => null,

    // HTTP tuning.
    'http' => [
        'connect_timeout' => 10,
        'request_timeout' => 60,
    ],

    // Only these invoice statuses are ever considered for KSeF submission.
    // Invoices are handed over once paid, so this is effectively ['paid'].
    'send_statuses' => ['paid'],
];
