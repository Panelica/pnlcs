<?php

namespace App\Translation;

use Illuminate\Translation\FileLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DbTranslationLoader extends FileLoader
{
    public function load($locale, $group, $namespace = null): array
    {
        if ($namespace && $namespace !== '*') {
            return parent::load($locale, $group, $namespace);
        }

        $cacheKey = "translations:{$locale}:{$group}";

        return Cache::remember($cacheKey, 3600, function () use ($locale, $group, $namespace) {
            // File-based translations (fallback)
            $fileTranslations = parent::load($locale, $group, $namespace);

            // DB translations
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
        });
    }
}
