<?php

use App\Support\ConnectionFailure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\ConnectionException;

/*
 * A call that ended without an answer either never reached the other server or
 * reached it and was not answered. Only the second may have done its work
 * there, so only then is "try again" a risk of doing it twice.
 */

function connectionFailureOf(string $message, array $context = []): ConnectionException
{
    return new ConnectionException($message, 0, new ConnectException($message, new Request('POST', 'https://panel.test:8443/v1/emails'), null, $context));
}

test('a request cURL sent is one the server may have acted on', function () {
    expect(ConnectionFailure::requestWasSent(connectionFailureOf(
        'cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received', ['errno' => 28, 'request_size' => 312]
    )))->toBeTrue();
});

test('nothing sent means the server never got it, whatever the message says', function (string $message) {
    expect(ConnectionFailure::requestWasSent(connectionFailureOf($message, ['errno' => 28, 'request_size' => 0])))->toBeFalse();
})->with([
    'connect timeout' => ['cURL error 28: Connection timed out after 5001 milliseconds'],
    'timeout wording, no bytes' => ['cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received'],
]);

test('without cURL figures the message decides', function (string $message, bool $sent) {
    expect(ConnectionFailure::requestWasSent(connectionFailureOf($message)))->toBe($sent);
})->with([
    'refused' => ['cURL error 7: Failed to connect to panel.test port 8443: Connection refused', false],
    'not resolved' => ['cURL error 6: Could not resolve host: panel.test', false],
    'connect timeout' => ['cURL error 28: Connection timed out after 5001 milliseconds', false],
    'transfer timeout' => ['cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received', true],
    'closed without a reply' => ['cURL error 52: Empty reply from server', true],
]);

test('an exception with nothing underneath is read by its message', function () {
    expect(ConnectionFailure::requestWasSent(new ConnectionException('cURL error 7: Failed to connect')))->toBeFalse()
        ->and(ConnectionFailure::requestWasSent(new ConnectionException('cURL error 28: Operation timed out after 30001 milliseconds')))->toBeTrue();
});
