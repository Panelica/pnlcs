<?php

namespace App\Services;

use App\Models\DockerApp;
use App\Support\ServerAddress;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Fills in catalogue images, from the panel's own links and from a public icon set.
 *
 * Two sources, because neither covers the catalogue on its own: the panel has a
 * link for about a quarter of its apps and half of those are dead, and the icon
 * set only has what its maintainers have drawn. Whatever is still missing keeps
 * the letter tile, which is a finished-looking answer rather than a gap.
 *
 * Shared by the admin screen and the console command so both behave the same.
 */
class DockerAppImporter
{
    private const MAX_BYTES = 512 * 1024;

    /** Community icon set, addressed by app slug. */
    private const ICON_SET = 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/png/%s.png';

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @return array{done:int, failed:int, skipped:int, none:int}
     */
    public function importMany(array $templates, bool $overwrite = false, bool $useIconSet = true, ?callable $progress = null): array
    {
        $have = DockerApp::pluck('slug')->all();
        $r = ['done' => 0, 'failed' => 0, 'skipped' => 0, 'none' => 0];

        foreach ($templates as $t) {
            $slug = (string) ($t['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            if (! $overwrite && in_array($slug, $have, true)) {
                $r['skipped']++;
                $progress && $progress($slug, 'skipped');

                continue;
            }

            $candidates = array_values(array_filter([
                trim((string) ($t['logo_url'] ?? '')),
                $useIconSet ? sprintf(self::ICON_SET, rawurlencode($slug)) : '',
            ]));

            if ($candidates === []) {
                $r['none']++;
                $progress && $progress($slug, 'none');

                continue;
            }

            $ok = false;
            foreach ($candidates as $url) {
                if ($this->import($slug, $url) === true) {
                    $ok = true;
                    break;
                }
            }
            $ok ? $r['done']++ : $r['failed']++;
            $progress && $progress($slug, $ok ? 'done' : 'failed');
        }

        return $r;
    }

    /** @return true|string true, or why it could not be fetched */
    public function import(string $slug, string $url)
    {
        // Each hop is checked before it is requested: the address an operator
        // pastes (or the panel's catalogue names) must be on the public
        // internet, not this server, its network or a cloud metadata service.
        for ($hop = 0; ; $hop++) {
            $ip = $this->publicAddressFor($url);
            if ($ip === null) {
                return 'not a public address';
            }

            try {
                $resp = Http::timeout(12)->withHeaders(['User-Agent' => 'PNLCS'])
                    ->withoutRedirecting()
                    // Connect to the address that was checked, not to whatever
                    // the name resolves to a moment later.
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => [$this->resolveEntry($url, $ip)]]])
                    ->get($url);
            } catch (\Throwable $e) {
                return $e->getMessage();
            }

            if (! $resp->redirect()) {
                break;
            }
            $next = (string) $resp->header('Location');
            if ($hop >= 2 || $next === '') {
                return 'too many redirects';
            }
            $url = (string) UriResolver::resolve(new Uri($url), new Uri($next));
        }

        if (! $resp->successful()) {
            return 'HTTP '.$resp->status();
        }

        $body = $resp->body();
        if ($body === '') {
            return 'empty response';
        }
        if (strlen($body) > self::MAX_BYTES) {
            return 'larger than 512 KB';
        }

        // What the bytes are, not what the address or the server says they are.
        $ext = self::imageExtension($body);
        if ($ext === null) {
            return 'not an image';
        }

        $this->store($slug, $body, $ext, 'fetch');

        return true;
    }

    /**
     * The extension the stored file gets, read from its content; null for
     * anything that is not a plain image.
     *
     * The file is served from the public disk under that extension, so a name
     * taken from the upload or the URL could make it a page on the panel's own
     * address. An SVG is a document that can carry script: it is kept only
     * without scripts, event handlers, embedded documents or outside links.
     */
    public static function imageExtension(string $bytes): ?string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => self::isPlainSvg($bytes) ? 'svg' : null,
            default => null,
        };
    }

    private static function isPlainSvg(string $bytes): bool
    {
        if (stripos($bytes, '<!DOCTYPE') !== false || stripos($bytes, '<!ENTITY') !== false) {
            return false;
        }

        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($bytes, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $doc->documentElement || strtolower($doc->documentElement->localName) !== 'svg') {
            return false;
        }

        foreach ($doc->getElementsByTagName('*') as $el) {
            if (in_array(strtolower($el->localName), ['script', 'foreignobject', 'iframe', 'embed', 'object'], true)) {
                return false;
            }
            foreach ($el->attributes as $attr) {
                $name = strtolower($attr->localName);
                $value = strtolower(trim($attr->value));
                if (str_starts_with($name, 'on')) {
                    return false;
                }
                if ($name === 'href' && $value !== '' && ! str_starts_with($value, '#')) {
                    return false;
                }
                if (str_contains($value, 'javascript:')) {
                    return false;
                }
            }
        }

        return true;
    }

    /** The public IPv4 address to connect to for $url, or null. */
    private function publicAddressFor(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ServerAddress::resolve($host);
        if ($addresses === []) {
            return null;
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $addresses[0];
    }

    private function resolveEntry(string $url, string $ip): string
    {
        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        return strtolower($parts['host']).':'.$port.':'.$ip;
    }

    /** Write the image and point the app at it, replacing whatever was there. */
    public function store(string $slug, string $bytes, string $ext, string $source): void
    {
        $existing = DockerApp::where('slug', $slug)->first();
        if ($existing) {
            Storage::disk('public')->delete($existing->path);
        }

        // The name carries a content hash so a replaced image is not served
        // from a browser cache under its old name.
        $path = 'docker-apps/'.$slug.'-'.substr(md5($bytes), 0, 8).'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        DockerApp::updateOrCreate(['slug' => $slug], ['path' => $path, 'source' => $source]);
    }
}
