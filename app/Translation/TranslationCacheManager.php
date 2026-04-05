<?php

namespace App\Translation;

use Illuminate\Support\Facades\Cache;

class TranslationCacheManager
{
    public static function flush(): void
    {
        // Get all active locales from DB
        try {
            $locales = \App\Models\Language::where('is_active', true)->pluck('code')->toArray();
        } catch (\Throwable $e) {
            $locales = ['en'];
        }

        $groups = ['common', 'admin', 'client', 'auth', 'email', 'messages', 'validation', 'invoice', 'support', 'domain'];

        foreach ($locales as $locale) {
            foreach ($groups as $group) {
                Cache::forget("translations:{$locale}:{$group}");
            }
        }
    }

    public static function flushLocale(string $locale): void
    {
        $groups = ['common', 'admin', 'client', 'auth', 'email', 'messages', 'validation', 'invoice', 'support', 'domain'];

        foreach ($groups as $group) {
            Cache::forget("translations:{$locale}:{$group}");
        }
    }

    public static function flushKey(string $locale, string $group): void
    {
        Cache::forget("translations:{$locale}:{$group}");
    }
}
