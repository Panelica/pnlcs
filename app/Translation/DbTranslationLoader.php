<?php

namespace App\Translation;

use Illuminate\Translation\FileLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DbTranslationLoader extends FileLoader
{
    /** Seconds a merged group stays cached. */
    private const TTL = 3600;

    public function load($locale, $group, $namespace = null): array
    {
        if ($namespace && $namespace !== '*') {
            return parent::load($locale, $group, $namespace);
        }

        $cacheKey = "translations:{$locale}:{$group}";

        // The cached entry carries the state of the lang files it was built
        // from. Editing lang/<locale>/<group>.php therefore invalidates it on
        // its own: without this, an edit stayed invisible for up to an hour
        // and the page showed the raw key, which cost us the same debugging
        // session more than once.
        //
        // The DB side does not need a stamp — every write path already calls
        // TranslationCacheManager — and asking the database for its newest
        // row on every group load would undo the point of caching.
        $stamp = $this->fileStamp($locale, $group);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)
            && array_key_exists('lines', $cached)
            && is_array($cached['lines'])
            && ($cached['stamp'] ?? null) === $stamp) {
            return $cached['lines'];
        }

        $lines = $this->merged($locale, $group, $namespace);

        Cache::put($cacheKey, ['stamp' => $stamp, 'lines' => $lines], self::TTL);

        return $lines;
    }

    /** File translations with the database layered over them. */
    private function merged($locale, $group, $namespace): array
    {
        $fileTranslations = parent::load($locale, $group, $namespace);

        $dbTranslations = [];
        try {
            $rows = DB::table('dynamic_translations')
                ->where('language', $locale)
                ->where('group', $group)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->get(['key', 'value']);

            foreach ($rows as $row) {
                // Support nested keys: "section.element" → ['section']['element']
                data_set($dbTranslations, $row->key, $row->value);
            }
        } catch (\Throwable $e) {
            // DB not ready (install/migrate) — silently use file-only
        }

        // DB overrides file
        return array_replace_recursive($fileTranslations, $dbTranslations);
    }

    /**
     * Fingerprint of the lang files behind one group: size and mtime of each
     * candidate file, in the same paths FileLoader::loadPaths() reads. A file
     * that does not exist yet is part of the fingerprint too, so creating one
     * invalidates the entry as surely as editing one.
     */
    private function fileStamp(string $locale, string $group): string
    {
        $parts = [];

        foreach ((array) $this->paths as $path) {
            $full = "{$path}/{$locale}/{$group}.php";
            // clearstatcache() is deliberately not called: PHP's per-request
            // stat cache is what keeps this cheap, and a file written during
            // the same request is not a case that arises in production.
            $parts[] = is_file($full) ? (filemtime($full).':'.filesize($full)) : '-';
        }

        return md5(implode('|', $parts));
    }
}
