<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * The half of the translations that nothing was watching.
 *
 * A string in this product comes from one of two places: lang/<locale>/*.php,
 * or the dynamic_translations table, which app/Translation/DbTranslationLoader
 * layers over the files. TranslationParityTest covers the files and covers
 * them well - lang/tr carries every one of lang/en's 4471 keys.
 *
 * Nothing covered the table. Around forty migrations seed it and almost all of
 * them insert English alone, so it drifted to 365 English rows against 56
 * Turkish ones: 309 strings where a Turkish customer read English. Three of
 * those were plurals and fell through to the English form, which is how it was
 * found - "5 websites" on a Turkish page, where Turkish says "5 web sitesi"
 * and takes no plural suffix after a number at all.
 *
 * Turkish is enforced at zero because it is a language this product ships
 * complete. The other locales are left to the admin auto-translate; asserting
 * on them here would freeze a gap nobody has undertaken to close.
 */
test('every English row in the translation table has a Turkish one', function () {
    $english = DB::table('dynamic_translations')
        ->where('language', 'en')
        ->get(['group', 'key'])
        ->map(fn ($row) => $row->group.'|'.$row->key)
        ->all();

    // If this is empty the seeding migrations did not run and the test below
    // would pass for the wrong reason.
    expect($english)->not->toBeEmpty();

    $turkish = DB::table('dynamic_translations')
        ->where('language', 'tr')
        ->get(['group', 'key'])
        ->map(fn ($row) => $row->group.'|'.$row->key)
        ->all();

    $missing = array_values(array_diff($english, $turkish));

    expect($missing)->toBe([], sprintf(
        "%d keys have English in dynamic_translations and no Turkish. Seed them in a\n".
        "migration alongside the English, the way\n".
        "2026_09_20_100000_seed_turkish_client_hosting_translations does:\n%s",
        count($missing),
        implode("\n", array_slice($missing, 0, 20))
    ));
});

/*
 * The reported bug, in the shape the customer met it.
 *
 * These three keys are read with trans_choice(), which picks a side of the
 * "{1} …|[2,*] …" pair. With no Turkish row the Turkish page fell back to
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
test('Turkish keeps the placeholders English declares', function () {
    $rows = DB::table('dynamic_translations')
        ->whereIn('language', ['en', 'tr'])
        ->get(['language', 'group', 'key', 'value']);

    $byLanguage = [];
    foreach ($rows as $row) {
        $byLanguage[$row->language][$row->group.'|'.$row->key] = (string) $row->value;
    }

    $placeholders = function (string $value): array {
        // ":run" inside "artisan schedule:run" is part of a command example,
        // not a placeholder; Laravel only replaces what it is given.
        preg_match_all('/(?<![a-zA-Z0-9]):([a-zA-Z_]+)/', $value, $matches);
        $found = array_unique($matches[1]);
        sort($found);

        return $found;
    };

    $broken = [];
    foreach ($byLanguage['en'] ?? [] as $key => $english) {
        if (! isset($byLanguage['tr'][$key])) {
            continue;
        }

        $want = $placeholders($english);
        $have = $placeholders($byLanguage['tr'][$key]);

        if (array_diff($want, $have)) {
            $broken[] = sprintf('%s expects :%s, Turkish has %s', $key, implode(' :', $want), $have ? ':'.implode(' :', $have) : 'none');
        }
    }

    expect($broken)->toBe([]);
});

/*
 * THE OTHER LOCALES: REPORTED, NOT ENFORCED. Read this before adding an
 * assertion here.
 *
 * English and Turkish are the two languages this product ships complete, so
 * they are the two this file holds to zero. The rest are nowhere near it and
 * pretending otherwise would help nobody: measured against the 469 English
 * rows in dynamic_translations, Chinese has 21 and the other twenty-seven have
 * 11 each. Failing the build on that number would mean every migration that
 * seeds one English string breaks the suite until somebody produces the same
 * string in twenty-eight languages - which is how a test gets deleted rather
 * than satisfied. Those locales are the admin auto-translate's job, and the
 * file side of them already has a ratchet of its own in
 * tests/Feature/TranslationParityTest.php.
 *
 * So this test asserts nothing about them. It prints where they stand, once
 * per run, so the number is in front of whoever reads the output instead of
 * being discovered by a customer. It fails only if the report cannot be
 * produced at all - an empty table means the seeding migrations did not run and
 * every other test in this file passed for the wrong reason.
 */
test('the other locales are reported, not enforced', function () {
    $keysFor = fn (string $locale) => DB::table('dynamic_translations')
        ->where('language', $locale)
        ->get(['group', 'key'])
        ->map(fn ($row) => $row->group.'|'.$row->key)
        ->all();

    $english = $keysFor('en');
    expect($english)->not->toBeEmpty();

    $locales = DB::table('dynamic_translations')->distinct()->pluck('language')->all();
    sort($locales);

    $covered = [];
    foreach ($locales as $locale) {
        if ($locale === 'en') {
            continue;
        }

        $covered[$locale] = count(array_intersect($english, $keysFor($locale)));
    }

    expect($covered)->not->toBeEmpty()
        ->and($covered)->toHaveKey('tr')
        ->and($covered['tr'])->toBe(count($english));

    // Turkish on its own line because it is the one being enforced; the rest
    // grouped by how far along they are, so the report is three lines rather
    // than thirty and a locale that moves is visible at a glance.
    $turkish = $covered['tr'];
    unset($covered['tr']);

    $byCoverage = [];
    foreach ($covered as $locale => $count) {
        $byCoverage[$count][] = $locale;
    }
    krsort($byCoverage);

    $line = fn (string $label, int $count, string $locales) => sprintf(
        "    %-9s %4d/%d  %s\n", $label, $count, count($english), $locales
    );

    $report = sprintf("\n  dynamic_translations: %d English rows.\n", count($english));
    $report .= $line('enforced', $turkish, 'tr');
    foreach ($byCoverage as $count => $group) {
        $report .= $line('reported', $count, implode(' ', $group));
    }

    fwrite(STDOUT, $report);
});

/*
 * PRESENCE WAS THE LAST ROUND'S TEST. THIS ONE IS ABOUT WHAT IS IN THE ROW.
 *
 * 'every English row in the translation table has a Turkish one' compares key
 * sets, exactly the way TranslationParityTest's 'the complete languages stay
 * complete' does - and that test passed while the Turkish home page said "Shd
 * Sunucuing". A row seeded as a copy of the English satisfies it perfectly: the
 * key is there, the language column says 'tr', and the customer reads English.
 *
 * That is not a hypothetical here. These 469 rows are the client hosting area -
 * files, databases, DNS, cron, email, containers - and they were seeded by hand
 * in a migration this round. Seeding the English into the Turkish row is the
 * single easiest way to make the test above green, and it is the thing this
 * test exists to refuse.
 *
 * The exceptions are listed rather than counted, because each one was judged:
 * a protocol, a product name, or a placeholder-and-unit string that reads the
 * same in both languages. 'Terminal' is Turkish for terminal. 'Port' is
 * Turkish for port. ':value RAM' has no Turkish in it to get wrong.
 */
const IDENTICAL_TURKISH_ROWS = [
    'client|hosting.containers.terminal',    // Terminal
    'client|hosting.cron.command_ph',        // a shell command, copied not read
    'client|hosting.cron.ex.wp',             // WordPress cron
    'client|hosting.dashboard.cpu',          // CPU
    'client|hosting.databases.phpmyadmin',   // phpMyAdmin
    'client|hosting.dns.ttl',                // TTL
    'client|hosting.email.settings_port',    // Port
    'client|hosting.ftp.port',               // Port
    'client|hosting.subdomains.php',         // PHP
    'client|hosting.tools.laravel',          // Laravel
    'client|hosting.tools.nodejs',           // Node.js
    'client|hosting.tools.python',           // Python
    'client|store.res_cpu',                  // :value vCPU
    'client|store.res_memory',               // :value RAM
];

test('a Turkish row is Turkish, not the English copied across', function () {
    $rows = DB::table('dynamic_translations')
        ->whereIn('language', ['en', 'tr'])
        ->get(['language', 'group', 'key', 'value']);

    $byLanguage = [];
    foreach ($rows as $row) {
        $byLanguage[$row->language][$row->group.'|'.$row->key] = (string) $row->value;
    }

    // An empty table would make every assertion below pass for the wrong
    // reason - the seeding migrations not having run.
    expect($byLanguage['en'] ?? [])->not->toBeEmpty();

    $identical = [];
    foreach ($byLanguage['en'] as $key => $english) {
        if (! isset($byLanguage['tr'][$key])) {
            continue;   // the test above owns missing rows
        }
        // A value with no letter in it - ':count', '%', '—' - carries no
        // language, so it cannot have been left untranslated.
        if (! preg_match('/\p{L}/u', $english)) {
            continue;
        }
        if ($byLanguage['tr'][$key] === $english) {
            $identical[] = $key;
        }
    }

    $unexpected = array_values(array_diff($identical, IDENTICAL_TURKISH_ROWS));

    expect($unexpected)->toBe([], sprintf(
        "%d rows have the English string sitting in the Turkish row. The key\n".
        "test passes on these and the customer still reads English. Translate\n".
        "them in a migration, the way\n".
        "2026_09_20_100000_seed_turkish_client_hosting_translations does. If the\n".
        "string is a protocol, a product name or a unit that Turkish does not\n".
        "translate, add it to IDENTICAL_TURKISH_ROWS in\n".
        "tests/Feature/DynamicTranslationParityTest.php with the value in a\n".
        "comment, so the next reader can see it was judged and not swept:\n%s",
        count($unexpected),
        implode("\n", $unexpected)
    ));

    // The other direction. A listed key that is NO LONGER identical has been
    // translated, and leaving its line here would licence somebody to put the
    // English back; a listed key that has vanished is a line about nothing.
    $stale = array_values(array_diff(IDENTICAL_TURKISH_ROWS, $identical));

    expect($stale)->toBe([], sprintf(
        "These keys are listed in IDENTICAL_TURKISH_ROWS but are no longer a\n".
        "copy of the English - they were translated, or the row is gone. Delete\n".
        "their lines from tests/Feature/DynamicTranslationParityTest.php:\n%s",
        implode("\n", $stale)
    ));
});
