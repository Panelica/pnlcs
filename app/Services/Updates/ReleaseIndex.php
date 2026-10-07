<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The published releases, read from GitHub Releases (or, for the update lab, a
 * file of the same shape). A draft is not a release: turning a harmful
 * release back into a draft withdraws it from every updater (RELEASING.md).
 */
class ReleaseIndex
{
    public const STABLE = 'stable';

    public const BETA = 'beta';

    /** @var array<int, Release>|null */
    private ?array $releases = null;

    public function __construct(private readonly ?string $url = null) {}

    /**
     * What went wrong reaching a release server, in a sentence the admin area
     * can show: never the body of the answer.
     */
    public static function describeFailure(\Throwable $e, string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;

        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            $response = $e->response;
            if (in_array($response->status(), [403, 429], true) && $response->header('X-RateLimit-Remaining') === '0') {
                $reset = (int) $response->header('X-RateLimit-Reset');

                return "{$host} refused: this server's address has used up its limit of requests"
                    .($reset > 0 ? ' until '.\Illuminate\Support\Carbon::createFromTimestamp($reset)->format('H:i').' UTC' : '')
                    .'. Try again then.';
            }

            return "{$host} answered with HTTP status {$response->status()}.";
        }

        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return "{$host} could not be reached: ".mb_strimwidth($e->getMessage(), 0, 160, '...');
        }

        return mb_strimwidth($e->getMessage(), 0, 200, '...');
    }

    /** @return array<int, Release> newest first */
    public function releases(): array
    {
        if ($this->releases !== null) {
            return $this->releases;
        }

        $url = $this->url ?? (string) config('updates.index_url');
        try {
            $json = str_starts_with($url, 'file://')
                ? json_decode((string) @file_get_contents(substr($url, 7)), true)
                : Http::timeout(20)->withHeaders(['Accept' => 'application/vnd.github+json'])->get($url)->throw()->json();
        } catch (\Throwable $e) {
            throw new RuntimeException(self::describeFailure($e, $url), 0, $e);
        }

        if (! is_array($json)) {
            throw new RuntimeException('The release index could not be read.');
        }

        $releases = [];
        foreach ($json as $item) {
            if (! is_array($item) || ! empty($item['draft'])) {
                continue;
            }

            $version = Version::parse((string) ($item['tag_name'] ?? ''));
            if ($version === null || $version->isDevelopment()) {
                continue;
            }

            $assets = [];
            foreach ($item['assets'] ?? [] as $asset) {
                if (isset($asset['name'], $asset['browser_download_url'])) {
                    $assets[$asset['name']] = $asset['browser_download_url'];
                }
            }

            $release = new Release($version, (string) $item['tag_name'], (bool) ($item['prerelease'] ?? false) || $version->isPreRelease(), (string) ($item['body'] ?? ''), $item['published_at'] ?? null, $item['html_url'] ?? null, $assets);

            if ($release->isComplete()) {
                $releases[] = $release;
            }
        }

        usort($releases, fn (Release $a, Release $b) => $b->version->compare($a->version));

        return $this->releases = $releases;
    }

    /** The newest release a channel offers above the installed version. */
    public function latest(string $channel, ?Version $installed): ?Release
    {
        foreach ($this->releases() as $release) {
            if ($release->preRelease && $channel !== self::BETA) {
                continue;
            }

            return $installed === null || $release->version->greaterThan($installed) ? $release : null;
        }

        return null;
    }

    public function find(string $version): ?Release
    {
        $wanted = Version::parse($version);

        foreach ($wanted ? $this->releases() : [] as $release) {
            if ($release->version->compare($wanted) === 0) {
                return $release;
            }
        }

        return null;
    }
}
