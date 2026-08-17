<?php

namespace App\Services;

use App\Models\DockerApp;
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
    private const ACCEPTED = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'];

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
        try {
            $resp = Http::timeout(12)->withHeaders(['User-Agent' => 'PNLCS'])->get($url);
        } catch (\Throwable $e) {
            return $e->getMessage();
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

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (! in_array($ext, self::ACCEPTED, true)) {
            $type = (string) $resp->header('Content-Type');
            $ext = match (true) {
                str_contains($type, 'svg') => 'svg',
                str_contains($type, 'png') => 'png',
                str_contains($type, 'webp') => 'webp',
                str_contains($type, 'gif') => 'gif',
                str_contains($type, 'jpeg') => 'jpg',
                default => '',
            };
        }
        if ($ext === '') {
            return 'not an image';
        }

        $this->store($slug, $body, $ext, 'fetch');

        return true;
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
