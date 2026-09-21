<?php

use App\Services\WidgetManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Translations reach a raw echo without being spelled `{!! __() !!}`.
 *
 * The guard written for the marketing pages scans blades for a translation
 * echoed directly, and these three sinks print a VARIABLE that happens to hold
 * translated markup, so the guard could not see them and stayed green:
 *
 *   client/invoices/show.blade.php:195   the gateway's payment form
 *   admin/dashboard.blade.php:128,142    a widget's rendered html
 *   admin/config/registrars.blade.php:21 a registrar's configuration help
 *
 * All three print a string built by concatenating __() values, every one of
 * which an admin with manage_settings can rewrite through the translation
 * editor with no sanitising. The first of them renders in a paying customer's
 * browser.
 *
 * The fix differs per sink because the markup differs. A payment form and a
 * widget are markup the module builds, so the translations inside them are
 * escaped where they are concatenated, exactly as those modules already
 * escaped their data. A registrar's help is inline prose that legitimately
 * ships with <strong> and <br>, so it goes through the allow-list filter.
 */
function hostileTranslation(string $group, string $key, string $value): void
{
    DB::table('dynamic_translations')->updateOrInsert(
        ['language' => 'en', 'group' => $group, 'key' => $key],
        ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
    );

    Cache::flush();
}

test('no widget can be made to print a script through a translation', function () {
    // Every key any widget renders, poisoned at once: this asserts the rule
    // rather than one widget, so a twelfth widget added later is covered only
    // if it escapes like the other eleven.
    $keys = [];
    foreach (glob(app_path('Widgets/*.php')) as $file) {
        preg_match_all("/__\('([a-z_]+)\.([a-zA-Z0-9_.]+)'/", file_get_contents($file), $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            $keys[$hit[1].'|'.$hit[2]] = true;
        }
    }

    expect($keys)->not->toBeEmpty();

    foreach (array_keys($keys) as $pair) {
        [$group, $key] = explode('|', $pair, 2);
        hostileTranslation($group, $key, '<script>alert(1)</script>');
    }

    $rendered = 0;
    foreach (app(WidgetManager::class)->renderAll() as $item) {
        $html = $item['html'] ?? '';
        $rendered++;

        expect($html)->not->toContain('<script>');
    }

    expect($rendered)->toBeGreaterThan(0);
});

test('a registrar help text cannot smuggle a script into the admin screen', function () {
    hostileTranslation('admin', 'registrars.hrd_instructions', 'Help<script>alert(1)</script>');

    $filtered = (string) inline_markup(__('admin.registrars.hrd_instructions'));

    expect($filtered)->not->toContain('<script>')
        ->and($filtered)->toContain('&lt;script&gt;');
});

test('the markup a registrar help legitimately ships still renders', function () {
    hostileTranslation('admin', 'registrars.hrd_instructions', '<strong>Step one</strong><br>Then this.');

    $filtered = (string) inline_markup(__('admin.registrars.hrd_instructions'));

    expect($filtered)->toContain('<strong>Step one</strong>')
        ->and($filtered)->toContain('<br>');
});

test('no widget concatenates a translation without escaping it', function () {
    // THE RULE, NOT THE FOUR PLACES. A widget's render() builds markup, so the
    // dashboard has to print it raw; what must hold is that every translation
    // inside it was escaped where it was concatenated. A twelfth widget written
    // next month is covered by this and by nothing else.
    $offenders = [];

    foreach (glob(app_path('Widgets/*.php')) as $file) {
        $src = file_get_contents($file);

        if (! str_contains($src, 'function render')) {
            continue;
        }

        $body = substr($src, strpos($src, 'function render'));
        $body = substr($body, 0, strpos($body, "\n    }") + 6);

        // A translation call not immediately preceded by e(
        if (preg_match_all('/(?<!e\()__\(/', $body, $m)) {
            $offenders[] = basename($file).' ('.count($m[0]).')';
        }
    }

    expect($offenders)->toBe([]);
});

test('no blade prints a registrar help raw', function () {
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($files as $file) {
        if (! str_ends_with((string) $file, '.blade.php')) {
            continue;
        }

        foreach (preg_split('/\R/', file_get_contents((string) $file)) as $no => $line) {
            if (preg_match('/\{!!\s*\$reg->help/', $line)) {
                $offenders[] = str_replace(base_path().'/', '', (string) $file).':'.($no + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
