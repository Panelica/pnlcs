<?php

use Illuminate\Support\Arr;

/*
 * lang/en.json repeated 461 keys of the English group files ("common.form.
 * postcode" and so on). Laravel reads a locale's JSON before its group files,
 * so in English the JSON copy won: an edit to lang/en/common.php did not show
 * in English, and two labels already differed ("Postcode" there, "Postcode /
 * ZIP" in the group file every other language falls back to). The file is
 * gone; a JSON file that repeats a group key is not let back in.
 */

it('has no JSON translation file that repeats a key of the group files', function () {
    $shadowing = [];

    foreach (glob(lang_path('*.json')) as $file) {
        $locale = basename($file, '.json');
        foreach (array_keys(json_decode(file_get_contents($file), true) ?: []) as $key) {
            [$group, $rest] = array_pad(explode('.', (string) $key, 2), 2, null);
            $groupFile = lang_path("{$locale}/{$group}.php");
            if ($rest !== null && is_file($groupFile) && Arr::has(require $groupFile, $rest)) {
                $shadowing[] = "{$locale}.json: {$key}";
            }
        }
    }

    expect($shadowing)->toBe([]);
});

it('reads the English labels from the group files', function () {
    app()->setLocale('en');

    expect(__('common.form.postcode'))->toBe('Postcode / ZIP')
        ->and(__('common.form.state'))->toBe('State / Region');
});
