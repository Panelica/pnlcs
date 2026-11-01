<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Tpay\TpayModule;

/*
 * A Tpay notification names the certificate its signature is checked with
 * (the JWS x5u header). The panel fetches it, so it must be fetched from
 * Tpay's host and nowhere else: an address that merely starts with
 * "https://secure.tpay.com" is somebody else's.
 */
function tpayWebhookWithCertificateAt(string $x5u): array
{
    $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'x5u' => $x5u])), '+/', '-_'), '=');

    return (new TpayModule)->processWebhook([
        '_raw_payload' => 'id=1&tr_id=TR-1&tr_status=TRUE',
        '_signature_header' => $header.'..c2ln',
    ]);
}

test('a certificate address that only starts like Tpay\'s is not fetched', function (string $x5u) {
    Http::fake();

    expect(tpayWebhookWithCertificateAt($x5u)['success'])->toBeFalse();

    Http::assertNothingSent();
})->with([
    'longer host' => 'https://secure.tpay.com.example.net/x509/cert.pem',
    'user part' => 'https://secure.tpay.com@example.net/x509/cert.pem',
    'other port' => 'https://secure.tpay.com:8443/x509/cert.pem',
    'plain http' => 'http://secure.tpay.com/x509/cert.pem',
]);

test('a certificate on Tpay\'s host is still fetched', function () {
    Http::fake(['*' => Http::response('', 404)]);

    expect(tpayWebhookWithCertificateAt('https://secure.tpay.com/x509/notifications-jws.pem')['success'])->toBeFalse();

    Http::assertSent(fn (Request $r) => $r->url() === 'https://secure.tpay.com/x509/notifications-jws.pem');
});
