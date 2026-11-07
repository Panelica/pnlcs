<?php

use App\Services\RdapRegistrationDate;
use Illuminate\Support\Facades\Http;

test('reads the registration event date out of the RDAP answer', function () {
    Http::fake(['rdap.org/*' => Http::response([
        'objectClassName' => 'domain',
        'ldhName' => 'example.com',
        'events' => [
            ['eventAction' => 'registration', 'eventDate' => '1995-08-14T04:00:00Z'],
            ['eventAction' => 'expiration', 'eventDate' => '2030-08-14T04:00:00Z'],
        ],
    ], 200)]);

    expect(app(RdapRegistrationDate::class)->forDomain('example.com'))->toBe('1995-08-14');
});

test('returns null when RDAP has no registration event', function () {
    Http::fake(['rdap.org/*' => Http::response([
        'objectClassName' => 'domain',
        'ldhName' => 'example.com',
        'events' => [
            ['eventAction' => 'expiration', 'eventDate' => '2030-08-14T04:00:00Z'],
        ],
    ], 200)]);

    expect(app(RdapRegistrationDate::class)->forDomain('example.com'))->toBeNull();
});

test('returns null when RDAP does not know the domain', function () {
    Http::fake(['rdap.org/*' => Http::response(['errorCode' => 404, 'title' => 'Not Found'], 404)]);

    expect(app(RdapRegistrationDate::class)->forDomain('example.com'))->toBeNull();
});

test('returns null on a server error instead of throwing', function () {
    Http::fake(['rdap.org/*' => Http::response('oops', 500)]);

    expect(app(RdapRegistrationDate::class)->forDomain('example.com'))->toBeNull();
});
