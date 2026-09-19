<?php

use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A translation saved in the editor has to be the one the page shows.
 *
 * It was not. The loader wrote every database row with data_set(), so the key
 * "dashboard.welcome_back" became ['dashboard']['welcome_back'], while the
 * language files write that same key flat: 'dashboard.welcome_back' => '...'.
 * Laravel's Arr::get() tests the whole key against the array before it splits
 * on dots, so the file's English string was found first and the operator's
 * translation - loaded, merged, sitting right there - was never reached.
 *
 * 2501 of the keys in lang/en are flat, so the translation editor did not work
 * for almost anything. Reported as issue #45 by a German installation whose
 * dashboard kept saying "Welcome back" whatever was saved.
 *
 * Both shapes are exercised here, because the files contain both and a fix
 * that trades one for the other is no fix.
 */
function dbTrans(string $locale, string $group, string $key, string $value): void
{
    DB::table('dynamic_translations')->insert([
        'language' => $locale,
        'group' => $group,
        'key' => $key,
        'value' => $value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Cache::flush();
    app('translator')->setLocale($locale);
}

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    app('translator')->setLocale(config('app.locale'));
});

// ---------------------------------------------------------------------------
// the shape the files actually use, and the one from the report
// ---------------------------------------------------------------------------

test('a flat dotted key saved in the editor replaces the one from the file', function () {
    // 'dashboard.welcome_back' is flat in lang/en/client.php.
    expect(__('client.dashboard.welcome_back', ['name' => 'Ada']))
        ->toBe('Welcome back, Ada!');

    dbTrans('de', 'client', 'dashboard.welcome_back', 'Willkommen zurück, :name!');

    expect(__('client.dashboard.welcome_back', ['name' => 'Ada']))
        ->toBe('Willkommen zurück, Ada!');
});

test('placeholders still work in the translation the editor saved', function () {
    dbTrans('de', 'client', 'dashboard.welcome_back', ':name, willkommen zurück!');

    expect(__('client.dashboard.welcome_back', ['name' => 'Ada']))
        ->toBe('Ada, willkommen zurück!');
});

// ---------------------------------------------------------------------------
// the other shape, which must not be traded away for the first
// ---------------------------------------------------------------------------

test('a genuinely nested key saved in the editor replaces the one from the file', function () {
    // A real one: lang/en/client.php writes 'account' => ['email_change_needs_password' => ...],
    // so this key is reached by splitting on the dot rather than by a flat hit.
    // The files contain both shapes and a fix for one that breaks the other is
    // not a fix, so this is tested against the shipped file rather than an
    // invented one - addLines() would put it straight into the translator and
    // never reach the loader under test.
    $english = __('client.account.email_change_needs_password');

    expect($english)->not->toBe('client.account.email_change_needs_password')
        ->and($english)->not->toBe('');

    dbTrans('de', 'client', 'account.email_change_needs_password', 'Passwort erforderlich');

    expect(__('client.account.email_change_needs_password'))->toBe('Passwort erforderlich');
});

test('asking for a whole group sees the editor text, not the file text', function () {
    dbTrans('de', 'client', 'dashboard.welcome_back', 'Willkommen zurück, :name!');

    $group = trans('client');

    // The flat copy is what the translator reads; the nested copy is what a
    // caller walking the group array gets. They have to agree.
    expect($group['dashboard.welcome_back'])->toBe('Willkommen zurück, :name!')
        ->and(data_get($group, 'dashboard.welcome_back'))->toBe('Willkommen zurück, :name!');
});

// ---------------------------------------------------------------------------
// the collision the nested copy could cause, and does not
// ---------------------------------------------------------------------------

test('a short key and a longer one starting with it can both be saved', function () {
    dbTrans('de', 'client', 'dashboard', 'Übersicht');
    dbTrans('de', 'client', 'dashboard.welcome_back', 'Willkommen zurück, :name!');

    // Writing the second as a nested path would have replaced the first with an
    // array and lost it. Neither may disappear.
    expect(__('client.dashboard'))->toBe('Übersicht')
        ->and(__('client.dashboard.welcome_back', ['name' => 'Ada']))->toBe('Willkommen zurück, Ada!');
});

test('the same pair saved in the other order behaves identically', function () {
    dbTrans('de', 'client', 'dashboard.welcome_back', 'Willkommen zurück, :name!');
    dbTrans('de', 'client', 'dashboard', 'Übersicht');

    expect(__('client.dashboard'))->toBe('Übersicht')
        ->and(__('client.dashboard.welcome_back', ['name' => 'Ada']))->toBe('Willkommen zurück, Ada!');
});

// ---------------------------------------------------------------------------
// what must not change
// ---------------------------------------------------------------------------

test('a key nobody has translated still falls back to the file', function () {
    dbTrans('de', 'client', 'dashboard.welcome_back', 'Willkommen zurück, :name!');

    // A different key in the same group, untouched by the editor.
    expect(__('client.my_account'))->toBe(__('client.my_account', [], 'en'));
});

test('an empty database value is not allowed to blank a translation', function () {
    DB::table('dynamic_translations')->insert([
        'language' => 'de',
        'group' => 'client',
        'key' => 'dashboard.welcome_back',
        'value' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Cache::flush();
    app('translator')->setLocale('de');

    expect(__('client.dashboard.welcome_back', ['name' => 'Ada']))
        ->toBe('Welcome back, Ada!');
});

test('one locale does not borrow another locale rows', function () {
    dbTrans('de', 'client', 'dashboard.welcome_back', 'Willkommen zurück, :name!');

    app('translator')->setLocale('en');

    expect(__('client.dashboard.welcome_back', ['name' => 'Ada']))
        ->toBe('Welcome back, Ada!');
});

test('one group does not borrow another group rows', function () {
    dbTrans('de', 'client', 'dashboard.welcome_back', 'Willkommen zurück, :name!');

    // admin.dashboard.welcome_back is a different group; nothing was saved for it.
    expect(__('admin.dashboard.welcome_back'))->not->toBe('Willkommen zurück, :name!');
});
