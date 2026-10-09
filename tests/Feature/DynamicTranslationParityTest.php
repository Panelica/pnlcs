<?php

use App\Translation\OfficialTranslationRepository;
use App\Translation\TranslationCoverage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * The texts that used to live in the database.
 *
 * Around fifty migrations seeded dynamic_translations with the client hosting
 * area, the mail setup and SSL guides and the home page's apps section -
 * English and Turkish, a few German - and lang/en never had them. Everything
 * that reads the files saw half the product: the translation progress, "translate
 * missing", TranslationParityTest. German, Polish and Chinese showed those
 * pages in English and nothing noticed.
 *
 * Since 2026-10-09 those texts ship in lang/en, lang/tr, lang/de, lang/pl and
 * lang/zh, and 2026_10_09_000001 removes the database copies. What this file
 * holds now: no English text lives only in the database again, the complete
 * languages keep English's placeholders, and the list of texts written the same
 * as English on purpose is exact.
 */

const COMPLETE_LOCALES = ['tr', 'de', 'pl', 'zh'];

test('no English text lives only in the database', function () {
    // The migrations ran on this database, and 2026_10_09_000001 after them.
    // An English row that is still here says something lang/en does not: a
    // new text seeded into the table instead of written into the file, which
    // the files' tests, the progress and "translate missing" cannot see.
    $files = app(OfficialTranslationRepository::class)->forLocale('en');

    $onlyInDatabase = DB::table('dynamic_translations')
        ->where('language', 'en')
        ->get(['group', 'key', 'value'])
        ->filter(fn ($row) => ($files[$row->group][$row->key] ?? null) !== $row->value)
        ->map(fn ($row) => $row->group.'.'.$row->key)
        ->values()
        ->all();

    expect($onlyInDatabase)->toBe([], "Put these texts in lang/en (and the complete languages) instead of seeding them:\n".implode("\n", $onlyInDatabase));
});

test('no shipped text is shadowed by a database copy in a complete language', function () {
    $repository = app(OfficialTranslationRepository::class);

    $copies = [];
    foreach (array_merge(['en'], COMPLETE_LOCALES) as $locale) {
        $files = $repository->forLocale($locale);
        DB::table('dynamic_translations')->where('language', $locale)->get(['group', 'key', 'value'])
            ->each(function ($row) use ($files, $locale, &$copies) {
                if (($files[$row->group][$row->key] ?? null) === $row->value) {
                    $copies[] = $locale.' '.$row->group.'.'.$row->key;
                }
            });
    }

    expect($copies)->toBe([]);
});

/*
 * The reported bug, in the shape the customer met it.
 *
 * These three keys are read with trans_choice(), which picks a side of the
 * "{1} …|[2,*] …" pair. With no Turkish text the Turkish page fell back to
 * English and pluralised with -s. Turkish pluralises with -ler/-lar, and after
 * a number it takes no suffix: "5 web sitesi", never "5 web siteleri".
 */
test('a Turkish page counts in Turkish', function () {
    Cache::flush();
    app()->setLocale('tr');

    $cases = [
        // key => [singular, plural at five]
        'client.store.res_domains' => ['1 web sitesi', '5 web sitesi'],
        'client.hosting.containers.services' => ['1 konteyner', '5 konteyner'],
        'client.store.res_apps' => ['1 uygulama çalıştırın', '5 uygulamaya kadar çalıştırın'],
    ];

    foreach ($cases as $key => [$one, $five]) {
        expect(trans_choice($key, 1, ['count' => 1]))->toBe($one)
            ->and(trans_choice($key, 5, ['count' => 5]))->toBe($five);
    }
});

/*
 * A placeholder that does not survive translation is a broken string: Laravel
 * leaves ":count" on the page, or drops the number entirely.
 */
test('the complete languages keep the placeholders English declares', function () {
    $repository = app(OfficialTranslationRepository::class);
    $english = $repository->forLocale('en');

    $placeholders = function (string $value): array {
        // ":run" inside "artisan schedule:run" is part of a command example,
        // not a placeholder; Laravel only replaces what it is given.
        preg_match_all('/(?<![a-zA-Z0-9]):([a-zA-Z_]+)/', $value, $matches);
        $found = array_unique($matches[1]);
        sort($found);

        return $found;
    };

    $broken = [];
    foreach (COMPLETE_LOCALES as $locale) {
        $translated = $repository->forLocale($locale);
        foreach ($english as $group => $keys) {
            foreach ($keys as $key => $value) {
                if (! isset($translated[$group][$key])) {
                    continue;   // TranslationParityTest owns missing keys
                }
                $want = $placeholders((string) $value);
                if (array_diff($want, $placeholders((string) $translated[$group][$key]))) {
                    $broken[] = "{$locale} {$group}.{$key} expects :".implode(' :', $want);
                }
            }
        }
    }

    expect($broken)->toBe([]);
});

/*
 * The texts a complete language writes exactly as English does are listed one
 * by one in database/data/same_as_english.php, each judged: a product name, a
 * protocol, a unit, a word the language spells the same. The list is what lets
 * TranslationCoverage call those languages complete, so it has to be exact in
 * both directions - a text identical to English that is not on it is English
 * left behind, and a listed text that is no longer identical was translated
 * and must leave the list, or it would licence putting the English back.
 */
test('the list of texts written the same as English is exact', function () {
    $repository = app(OfficialTranslationRepository::class);
    $english = $repository->forLocale('en');
    $list = require database_path('data/same_as_english.php');

    $problems = [];
    foreach (COMPLETE_LOCALES as $locale) {
        $translated = $repository->forLocale($locale);
        $identical = [];
        foreach ($english as $group => $keys) {
            foreach ($keys as $key => $value) {
                if (trim((string) $value) === '' || TranslationCoverage::hasNoWords((string) $value)) {
                    continue;
                }
                if (($translated[$group][$key] ?? null) === $value) {
                    $identical[] = $group.'.'.$key;
                }
            }
        }

        foreach (array_diff($identical, $list[$locale] ?? []) as $key) {
            $problems[] = "{$locale} {$key} is still English: translate it, or list it if the language writes it so";
        }
        foreach (array_diff($list[$locale] ?? [], $identical) as $key) {
            $problems[] = "{$locale} {$key} is listed but no longer the same as English: remove it from the list";
        }
    }

    expect($problems)->toBe([]);
});
