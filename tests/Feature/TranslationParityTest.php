<?php

/*
 * Every language against English.
 *
 * Twenty-six of the thirty languages sit at exactly the same 2,921 keys, which
 * is what it looks like when a set of files is generated once and never touched
 * again. Chasing all of it down in one go is not realistic, so this test does
 * the next best thing: it writes today's gap down and fails when a gap grows.
 *
 * That means an English string added tomorrow cannot quietly widen the hole in
 * twenty-eight languages. Translate it, or lower the number here on purpose.
 * The numbers may only ever go down.
 */

/** @return array<string, true> every key in a locale, flattened to "file.dotted.key" */
function localeKeys(string $locale): array
{
    $flatten = function (array $rows, string $prefix) use (&$flatten): array {
        $out = [];
        foreach ($rows as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out += $flatten($value, $full);
            } else {
                $out[$full] = true;
            }
        }

        return $out;
    };

    $keys = [];
    foreach (glob(base_path('lang/'.$locale.'/*.php')) as $file) {
        $keys += $flatten((array) require $file, basename($file, '.php'));
    }

    return $keys;
}

// Measured 2026-08-20. Lower these as translations land; never raise them.
const TRANSLATION_GAP_BUDGET = [
    'pl' => 0,
    'zh' => 0,
    'tr' => 456,
    // The twenty-six that were generated together and left behind.
    'ar' => 984, 'az' => 984, 'ca' => 984, 'cs' => 984, 'da' => 984, 'de' => 984,
    'el' => 984, 'es' => 984, 'et' => 984, 'fa' => 984, 'fi' => 984, 'fr' => 984,
    'he' => 984, 'hr' => 984, 'hu' => 984, 'it' => 984, 'ja' => 984, 'ko' => 984,
    'mk' => 984, 'nl' => 984, 'no' => 984, 'pt-br' => 984, 'ro' => 984,
    'ru' => 984, 'sv' => 984, 'uk' => 984,
];

test('no language falls further behind English than it already is', function () {
    $english = localeKeys('en');
    expect($english)->not->toBeEmpty();

    $worse = [];
    foreach (array_keys(TRANSLATION_GAP_BUDGET) as $locale) {
        $missing = count(array_diff_key($english, localeKeys($locale)));
        $budget = TRANSLATION_GAP_BUDGET[$locale];
        if ($missing > $budget) {
            $worse[] = sprintf('%s is missing %d keys, budget %d', $locale, $missing, $budget);
        }
    }

    expect($worse)->toBe([], "Translate the new strings, or lower the budget deliberately:\n".implode("\n", $worse));
});

test('every shipped language is accounted for', function () {
    $shipped = array_map('basename', glob(base_path('lang/*'), GLOB_ONLYDIR));
    $tracked = array_merge(['en'], array_keys(TRANSLATION_GAP_BUDGET));

    // A language added without a budget line would never be checked again.
    expect(array_values(array_diff($shipped, $tracked)))->toBe([]);
});

test('the complete languages stay complete', function () {
    // Cheap to keep at parity, expensive to regain once they drift.
    foreach (['pl', 'zh'] as $locale) {
        expect(count(array_diff_key(localeKeys('en'), localeKeys($locale))))->toBe(0);
    }
});
