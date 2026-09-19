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
                // FLAT, AND NESTED ONLY WHERE IT CANNOT COLLIDE.
                //
                // This used to call data_set() alone, which turned
                // "dashboard.welcome_back" into ['dashboard']['welcome_back'].
                // The language files write the same key flat -
                // 'dashboard.welcome_back' => '...' - and Arr::get() tests the
                // whole key against the array BEFORE it splits on dots
                // (Illuminate/Collections/Arr.php: exists($array, $key) comes
                // first). So the file's flat English key was always found and
                // the nested database row was never reached: every translation
                // an operator saved through the editor for a dotted key was
                // loaded, merged, and then ignored. 2501 of the keys in lang/en
                // are flat, so that was almost all of them.
                //
                // Writing the key flat fixes both shapes at once. Against a
                // flat file key it replaces it outright; against a nested one
                // it still wins, because the flat lookup happens first.
                $dbTranslations[$row->key] = $row->value;

                // The nested copy as well, so that code asking for a whole
                // group - trans('client.dashboard') - sees the operator's text
                // rather than the file's. Skipped when a shorter key has
                // already put a string where this one needs an array, because
                // data_set() would overwrite it and lose a translation to fix
                // a lesser problem.
                if (str_contains($row->key, '.') && ! $this->nestingWouldClobber($dbTranslations, $row->key)) {
                    data_set($dbTranslations, $row->key, $row->value);
                }
            }
        } catch (\Throwable $e) {
            // DB not ready (install/migrate) — silently use file-only
        }

        // DB overrides file
        return array_replace_recursive($fileTranslations, $dbTranslations);
    }

    /**
     * Would writing this dotted key as a nested path destroy something?
     *
     * data_set() walks the path creating arrays as it goes, and where it finds
     * a string it replaces it. Two rows like "dashboard" and
     * "dashboard.welcome_back" therefore fight: whichever is written second
     * wins and the other is gone. The flat copy of both is already correct and
     * is what the translator reads first, so the nested copy is the one to skip.
     */
    private function nestingWouldClobber(array $translations, string $key): bool
    {
        $segments = explode('.', $key);
        array_pop($segments);

        $path = '';
        foreach ($segments as $segment) {
            $path = $path === '' ? $segment : $path.'.'.$segment;

            if (is_string(data_get($translations, $path))) {
                return true;
            }
        }

        return false;
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
