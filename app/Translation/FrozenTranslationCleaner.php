<?php

namespace App\Translation;

use Database\Seeders\TranslationSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Removes database translations that are only frozen copies of shipped text.
 *
 * A row in dynamic_translations always wins over lang/<locale>. Removed, and
 * only these - nothing an operator could have meant:
 *
 *  1. Any row whose text is exactly what its language file says now. Nobody
 *     sees a difference today, and the file's next improvement shows.
 *  2. English rows still holding TranslationSeeder's snapshot text where
 *     lang/en now says something else.
 *  3. Rows holding a text their language file once shipped and has since
 *     replaced (database/data/retired_translations.php).
 *
 * Rows that differ from the file in any other way stay: an operator's own
 * wording, the output of "Translate with AI", and rows that are deliberately
 * newer than the file (2026_08_17_230000_reword_apps_as_resource_hosting).
 *
 * Run by the migrations that ship it; see
 * 2026_10_05_120000_drop_frozen_copies_of_shipped_translations.
 */
class FrozenTranslationCleaner
{
    /** @return int the rows removed */
    public function run(): int
    {
        $official = app(OfficialTranslationRepository::class);
        $seeded = (new TranslationSeeder)->getTranslations();
        $retired = array_flip(require database_path('data/retired_translations.php'));
        $removed = 0;

        foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $locale) {
            $files = $official->forLocale($locale);
            $doomed = [];

            DB::table('dynamic_translations')
                ->where('language', $locale)
                ->orderBy('id')
                ->select(['id', 'group', 'key', 'value'])
                ->chunk(1000, function ($rows) use ($locale, $files, $seeded, $retired, &$doomed) {
                    foreach ($rows as $row) {
                        $value = (string) $row->value;
                        $shipped = $files[$row->group][$row->key] ?? null;

                        $copy = $shipped !== null && $value === $shipped;

                        $staleSeed = $locale === 'en'
                            && $shipped !== null
                            && ($seeded[$row->group][$row->key] ?? null) === $value;

                        $retiredText = isset($retired[self::fingerprint($locale, $row->group, $row->key, $value)]);

                        if ($copy || $staleSeed || $retiredText) {
                            $doomed[] = $row->id;
                        }
                    }
                });

            foreach (array_chunk($doomed, 500) as $ids) {
                $removed += DB::table('dynamic_translations')->whereIn('id', $ids)->delete();
            }

            TranslationCacheManager::flushLocale($locale);
        }

        return $removed;
    }

    /** How database/data/retired_translations.php names a text. */
    public static function fingerprint(string $locale, string $group, string $key, string $value): string
    {
        return substr(hash('sha256', $locale."\0".$group."\0".$key."\0".$value), 0, 20);
    }
}
