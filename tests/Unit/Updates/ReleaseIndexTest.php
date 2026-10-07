<?php

use App\Services\Updates\ReleaseIndex;
use App\Services\Updates\Version;
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

/** A release index as GitHub answers it: newest created first. */
function channelIndex(array $tags): ReleaseIndex
{
    $file = storage_path('framework/testing/index-'.bin2hex(random_bytes(5)).'.json');
    file_put_contents($file, json_encode(array_map(fn ($t) => [
        'tag_name' => 'v'.$t[0], 'prerelease' => $t[1], 'draft' => false, 'body' => '',
        'assets' => array_map(fn ($n) => ['name' => $n, 'browser_download_url' => 'file:///nonexistent/'.$n],
            ["pnlcs-{$t[0]}.tar.gz", "pnlcs-{$t[0]}.release.json", "pnlcs-{$t[0]}.release.json.sig"]),
    ], $tags)));

    return new ReleaseIndex('file://'.$file);
}

test('the stable channel never offers a beta; the beta channel offers both', function () {
    $index = channelIndex([['1.4.1-beta.1', true], ['1.4.0', false], ['1.3.0', false]]);

    expect($index->latest('stable', Version::parse('1.4.0')))->toBeNull()
        ->and((string) $index->latest('stable', Version::parse('1.3.0'))->version)->toBe('1.4.0')
        ->and((string) $index->latest('beta', Version::parse('1.4.0'))->version)->toBe('1.4.1-beta.1')
        ->and((string) $index->latest('beta', Version::parse('1.3.0'))->version)->toBe('1.4.1-beta.1');
});

test('a beta becomes the stable release of the same number, and the beta channel gets it too', function () {
    $index = channelIndex([['1.4.1', false], ['1.4.1-beta.2', true], ['1.4.1-beta.1', true], ['1.4.0', false]]);

    expect((string) $index->latest('beta', Version::parse('1.4.1-beta.2'))->version)->toBe('1.4.1')
        ->and((string) $index->latest('stable', Version::parse('1.4.0'))->version)->toBe('1.4.1');
});

test('the newest version is offered, not the newest published: a patch for an older line does not hide a beta (the list is sorted by version)', function () {
    // 1.4.2 was published after 1.5.0-beta.1, so GitHub lists it first.
    $index = channelIndex([['1.4.2', false], ['1.5.0-beta.1', true], ['1.4.1', false]]);

    expect((string) $index->latest('beta', Version::parse('1.4.1'))->version)->toBe('1.5.0-beta.1')
        ->and((string) $index->latest('beta', Version::parse('1.4.2'))->version)->toBe('1.5.0-beta.1')
        ->and((string) $index->latest('stable', Version::parse('1.4.1'))->version)->toBe('1.4.2');
});
