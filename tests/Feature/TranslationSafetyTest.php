<?php

use App\Translation\OfficialTranslationRepository;

/*
 * What every language must be before it reaches an install, complete or not.
 *
 * The community languages are not held to having every key (a key they lack
 * shows in English). They are held to this, because a translation that
 * arrives with an update reaches every install that uses the language, and
 * these are the faults a customer would see:
 *  - a file that does not load takes the whole language down;
 *  - a lost or renamed placeholder prints ":nmae" or drops the amount;
 *  - markup the English does not have is either shown as text or is markup
 *    nobody reviewed;
 *  - a missing key must fall back to English, never print its own name.
 */

/** @return list<string> every language folder except English */
function safetyLocales(): array
{
    return array_values(array_filter(
        array_map('basename', glob(base_path('lang/*'), GLOB_ONLYDIR)),
        fn ($locale) => $locale !== 'en'
    ));
}

/**
 * The placeholders an English text declares. Laravel replaces ":name" wherever
 * it stands, so a translation only has to contain each of them; in English a
 * colon after a letter ("schedule:run", "storage:snippets") is not one.
 */
function safetyPlaceholders(string $value): array
{
    preg_match_all('/(?<![a-zA-Z0-9]):([a-zA-Z_]+)/', $value, $matches);
    $found = array_unique($matches[1]);
    sort($found);

    return $found;
}

/** Real tags only: "<<<<<<<" conflict markers and "a < b" are not markup. */
function safetyTags(string $value): array
{
    preg_match_all('#</?[a-zA-Z][a-zA-Z0-9]*(?:\s[^<>]*)?/?>#', $value, $matches);
    $tags = array_map(fn ($tag) => strtolower((string) preg_replace('/\s+/', ' ', $tag)), $matches[0]);
    sort($tags);

    return $tags;
}

/*
 * Markup a translation carries that its English does not, judged one by one.
 * Each value here is rendered through App\Support\InlineMarkup's allow-list.
 */
const TRANSLATION_EXTRA_MARKUP = [
    // The accent colour on the second half of the home page headline
    // (.hero__title span); rendered with inline_markup().
    'tr sections.hero.title',
];

test('every language file loads and returns a list of texts', function () {
    foreach (glob(base_path('lang/*/*.php')) as $file) {
        $values = require $file;
        expect($values)->toBeArray("{$file} does not return an array");
    }
});

test('every language keeps the placeholders English declares', function () {
    $repository = app(OfficialTranslationRepository::class);
    $english = $repository->forLocale('en');

    $broken = [];
    foreach (safetyLocales() as $locale) {
        $translated = $repository->forLocale($locale);
        foreach ($english as $group => $keys) {
            foreach ($keys as $key => $value) {
                if (! isset($translated[$group][$key])) {
                    continue;
                }
                // Chinese and Japanese write ":item" straight after a word, with no
                // space; Laravel still fills it in, so a plain search is the test.
                $lost = array_filter(safetyPlaceholders((string) $value),
                    fn ($name) => ! str_contains((string) $translated[$group][$key], ':'.$name));
                if ($lost !== []) {
                    $broken[] = sprintf('%s %s.%s: the translation lost :%s', $locale, $group, $key, implode(' :', $lost));
                }
            }
        }
    }

    expect($broken)->toBe([], "A placeholder that does not match prints its name or loses its value:\n".implode("\n", $broken));
});

test('no language adds markup its English does not have', function () {
    $repository = app(OfficialTranslationRepository::class);
    $english = $repository->forLocale('en');

    $different = [];
    foreach (safetyLocales() as $locale) {
        $translated = $repository->forLocale($locale);
        foreach ($english as $group => $keys) {
            foreach ($keys as $key => $value) {
                if (! isset($translated[$group][$key]) || in_array("{$locale} {$group}.{$key}", TRANSLATION_EXTRA_MARKUP, true)) {
                    continue;
                }
                if (safetyTags((string) $value) !== safetyTags((string) $translated[$group][$key])) {
                    $different[] = "{$locale} {$group}.{$key}";
                }
            }
        }
    }

    expect($different)->toBe([], "Keep exactly the tags the English has:\n".implode("\n", $different));
});

test('the judged extra markup is still there', function () {
    // A line here for markup that is gone would wave the next one through.
    $repository = app(OfficialTranslationRepository::class);
    foreach (TRANSLATION_EXTRA_MARKUP as $entry) {
        [$locale, $fullKey] = explode(' ', $entry, 2);
        [$group, $key] = explode('.', $fullKey, 2);
        $value = (string) ($repository->forLocale($locale)[$group][$key] ?? '');
        expect(safetyTags($value))->not->toBe([], "{$entry} no longer carries markup: remove its line");
    }
});

test('a key a language does not have shows in English, never as its name', function () {
    expect(config('app.fallback_locale'))->toBe('en');

    $repository = app(OfficialTranslationRepository::class);
    $english = $repository->forLocale('en');

    // A key a community language really lacks, read the way a page reads it.
    foreach (safetyLocales() as $locale) {
        $translated = $repository->forLocale($locale);
        foreach ($english as $group => $keys) {
            foreach ($keys as $key => $value) {
                if (isset($translated[$group][$key]) || str_contains((string) $value, '|')) {
                    continue;
                }
                app()->setLocale($locale);
                expect(__("{$group}.{$key}"))->toBe($value);

                return;
            }
        }
    }

    $this->markTestSkipped('every language has every key');
});

test('the safety checks can actually fail', function () {
    // A check that cannot fail would pass every broken contribution.
    expect(safetyTags('Hello <strong>you</strong>'))->not->toBe(safetyTags('Merhaba <strong>siz</strong><script>x()</script>'))
        ->and(safetyTags('<a href="/a">x</a>'))->not->toBe(safetyTags('<a href="/a" onclick="x()">x</a>'))
        ->and(safetyTags('Fix <<<<<<< and >>>>>>> markers'))->toBe([])
        ->and(safetyPlaceholders('Paid :amount on :date'))->toBe(['amount', 'date'])
        ->and(safetyPlaceholders('php artisan schedule:run'))->toBe([])
        ->and(str_contains('未找到:item。', ':item'))->toBeTrue();
});
