<?php

use App\Services\Updates\ReleaseIndex;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The release list as the admin area shows its failures: a sentence the
 * operator can act on, never a raw HTTP body.
 */
test('GitHub\'s request limit is explained, with the time it lifts', function () {
    $reset = now()->addMinutes(37)->getTimestamp();
    Http::fake(['*' => Http::response(['message' => 'API rate limit exceeded for 203.0.113.9.'], 403, ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => (string) $reset])]);

    expect(fn () => (new ReleaseIndex('https://api.github.com/repos/Panelica/pnlcs/releases'))->releases())
        ->toThrow(RuntimeException::class, 'limit of requests');
});

test('an answer that is not the release list names the status, not its body', function () {
    Http::fake(['*' => Http::response('<html>'.str_repeat('x', 5000).'</html>', 502)]);

    try {
        (new ReleaseIndex('https://api.github.com/repos/Panelica/pnlcs/releases'))->releases();
        $this->fail('no exception');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('502')->and(strlen($e->getMessage()))->toBeLessThan(300);
    }
});

test('a server that cannot be reached is said so', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host: api.github.com'));

    expect(fn () => (new ReleaseIndex('https://api.github.com/repos/Panelica/pnlcs/releases'))->releases())
        ->toThrow(RuntimeException::class, 'could not be reached');
});
