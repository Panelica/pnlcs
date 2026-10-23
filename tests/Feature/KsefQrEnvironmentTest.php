<?php

use App\Models\AddonSetting;
use App\Services\InvoicePdfService;

/**
 * The KSeF verification QR read config('ksef.environment'), the module file's
 * default ("integration"), not the environment the operator chose in the
 * addon settings. Production invoices pointed at ksef-test.
 */
function verifyUrl(string $number): string
{
    $service = app(InvoicePdfService::class);

    return (new ReflectionMethod($service, 'ksefVerifyUrl'))->invoke($service, $number);
}

it('points the QR at production when the addon is set to prod', function () {
    AddonSetting::setSetting('ksef', 'environment', 'prod');

    expect(verifyUrl('1234567890-20261022-ABC'))->toStartWith('https://ksef.mf.gov.pl/web/verify?nr=');
});

it('points the QR at the test site for the test environments', function (string $env) {
    AddonSetting::setSetting('ksef', 'environment', $env);

    expect(verifyUrl('1234567890-20261022-ABC'))->toStartWith('https://ksef-test.mf.gov.pl/web/verify?nr=');
})->with(['integration', 'demo']);
