<?php

use Illuminate\Support\Facades\Http;
use Modules\Registrars\OVH\OvhClient;

function ovhHeaderValue($request, string $name): ?string
{
    $value = $request->header($name);

    return is_array($value) ? (string) reset($value) : (string) $value;
}

test('signs requests with the OVHcloud signature scheme', function () {
    Http::fake(['https://eu.api.ovh.com/1.0/*' => Http::response(['firstname' => 'John'])]);

    $client = new OvhClient('app-key', 'app-secret', 'consumer-key', 'eu');
    $result = $client->get('/me');

    expect($result['firstname'])->toBe('John');

    Http::assertSent(function ($request) {
        $url = 'https://eu.api.ovh.com/1.0/me';
        $ts = ovhHeaderValue($request, 'X-Ovh-Timestamp');
        $expected = '$1$'.sha1('app-secret'.'+'.'consumer-key'.'+'.'GET'.'+'.$url.'+'.''.'+'.$ts);

        return ovhHeaderValue($request, 'X-Ovh-Application') === 'app-key'
            && ovhHeaderValue($request, 'X-Ovh-Consumer') === 'consumer-key'
            && ovhHeaderValue($request, 'X-Ovh-Signature') === $expected;
    });
});

test('selects the endpoint for the chosen region', function () {
    Http::fake(['https://ca.api.ovh.com/1.0/*' => Http::response([], 200)]);

    $client = new OvhClient('k', 's', 'c', 'ca');
    $client->get('/me');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://ca.api.ovh.com/1.0/'));
});

test('throws a readable error on an OVH API failure', function () {
    Http::fake(['https://eu.api.ovh.com/1.0/*' => Http::response(['message' => 'Not granted for this resource'], 403)]);

    $client = new OvhClient('k', 's', 'c', 'eu');

    expect(fn () => $client->get('/me'))
        ->toThrow(RuntimeException::class, 'OVH HTTP 403: Not granted for this resource');
});
