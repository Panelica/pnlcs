<?php

/*
 * A view's group key (__('common.actions.preview')) must resolve in every
 * shipped language, not only in English.
 *
 * Eight keys lived only in lang/en.json. Laravel reads a JSON file for the
 * current locale only; falling back to English goes through the group files
 * (lang/en/common.php), never en.json. So in Turkish, German, Polish or
 * Chinese those buttons and labels showed their own key - "common.actions.
 * preview" on the appearance builder.
 */

function jsonOnlyViewKeys(): array
{
    $json = json_decode(file_get_contents(lang_path('en.json')), true) ?: [];
    $used = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        preg_match_all("/(?:__|trans|trans_choice)\\(\\s*['\"]([a-z_]+\\.[A-Za-z0-9_.\\-]+)['\"]/", file_get_contents($file->getPathname()), $m);
        $used = array_merge($used, $m[1]);
    }

    return array_values(array_filter(array_unique($used), fn ($key) => array_key_exists($key, $json)));
}

it('resolves every view key that en.json carries in every shipped language', function () {
    $broken = [];
    foreach (['tr', 'de', 'pl', 'zh', 'en'] as $locale) {
        app()->setLocale($locale);
        foreach (jsonOnlyViewKeys() as $key) {
            if (__($key) === $key) {
                $broken[] = "{$locale}: {$key}";
            }
        }
    }

    expect($broken)->toBe([], "These keys exist only in lang/en.json, which is not a fallback; add them to the group file:\n".implode("\n", $broken));
});

it('shows the preview button in Turkish on the appearance page', function () {
    app()->setLocale('tr');

    expect(__('common.actions.preview'))->toBe('Önizle')
        ->and(__('common.status.failed'))->toBe('Başarısız');
});
