<?php

namespace App\Translation;

use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TranslationCacheManager
{
    public static function flush(): void
    {
        // Get all active locales from DB
        try {
            $locales = Language::where('is_active', true)->pluck('code')->toArray();
        } catch (\Throwable $e) {
            $locales = ['en'];
        }

        foreach ($locales as $locale) {
            self::flushLocale($locale);
        }
    }

    public static function flushLocale(string $locale): void
    {
        foreach (self::groups($locale) as $group) {
            Cache::forget("translations:{$locale}:{$group}");
        }
    }

    public static function flushKey(string $locale, string $group): void
    {
        Cache::forget("translations:{$locale}:{$group}");
    }

    /**
     * Every group a cached entry can exist for. The list used to be written
     * out by hand and had drifted: sections, pdf, proxmox, errors and group
     * were never flushed, so a saved or AI-translated text in one of them
     * stayed invisible until the hour-long cache ran out.
     *
     * @return list<string>
     */
    private static function groups(string $locale): array
    {
        $groups = ['common', 'admin', 'client', 'auth', 'email', 'messages', 'validation', 'invoice', 'support', 'domain'];

        foreach (glob(lang_path('en/*.php')) ?: [] as $file) {
            $groups[] = basename($file, '.php');
        }

        try {
            $groups = array_merge($groups, DB::table('dynamic_translations')
                ->where('language', $locale)->distinct()->pluck('group')->all());
        } catch (\Throwable $e) {
            // DB not ready (install/migrate): the file groups are enough.
        }

        return array_values(array_unique($groups));
    }
}
