<?php

/*
 * 2026_10_05_120000_drop_frozen_copies_of_shipped_translations, run against the
 * rows a real install carries.
 *
 * The Turkish rows below are the ones an operator reported from their editor:
 * old-file text that the editor's Save had frozen in the database, where it
 * outranked the rewritten lang/tr. The migration has to remove exactly those
 * copies and leave every text somebody actually chose.
 */

use App\Models\DynamicTranslation;
use App\Translation\OfficialTranslationRepository;
use Database\Seeders\TranslationSeeder;

function runFrozenCopiesMigration(): void
{
    $migration = require database_path('migrations/2026_10_05_120000_drop_frozen_copies_of_shipped_translations.php');
    $migration->up();
}

function frozenRow(string $locale, string $group, string $key, string $value): DynamicTranslation
{
    return DynamicTranslation::updateOrCreate(
        ['language' => $locale, 'group' => $group, 'key' => $key],
        ['value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true]
    );
}

test('the reported Turkish texts are removed and the files show again', function () {
    $reported = [
        '2fa.backup_codes' => 'Geriup Kods',
        '2fa.code' => 'Auntication Kod',
        'disabled' => 'Sizin account  devre disi birakildi.',
        'failed' => 'se credentials do not match our records.',
        'register.address' => 'Ekleress',
        'login.admin_title' => 'Yonetici Gunlukin',
        'login.no_account' => "Don't have an account?",
        'forgot.submit' => 'Gonder Sifirla Baglanti',
    ];
    foreach ($reported as $key => $value) {
        frozenRow('tr', 'auth', $key, $value);
    }

    runFrozenCopiesMigration();

    expect(DynamicTranslation::where(['language' => 'tr', 'group' => 'auth'])->whereIn('key', array_keys($reported))->count())->toBe(0);

    $files = app(OfficialTranslationRepository::class)->forLocale('tr')['auth'];
    app()->setLocale('tr');
    foreach (array_keys($reported) as $key) {
        expect(__('auth.'.$key))->toBe($files[$key]);
    }
    expect(__('auth.2fa.backup_codes'))->toBe('Yedek Kodlar');
});

test('the labels that read like their own keys are removed in every language', function () {
    frozenRow('en', 'admin', 'tickets.client_label', 'Client label');
    frozenRow('de', 'admin', 'affiliates.title_show', 'Titelshow');
    frozenRow('pl', 'admin', 'logs.status_sent', 'Status: wysłane');
    frozenRow('tr', 'admin', 'sidebar.shortcuts', 'Kisayollar');

    runFrozenCopiesMigration();

    expect(DynamicTranslation::whereIn('key', ['tickets.client_label', 'affiliates.title_show', 'logs.status_sent', 'sidebar.shortcuts'])->count())->toBe(0);
    app()->setLocale('en');
    expect(__('admin.tickets.client_label'))->toBe('Client:');
    app()->setLocale('tr');
    expect(__('admin.sidebar.shortcuts'))->toBe('Kısayollar');
});

test('an old text is only recognised in its own language', function () {
    // "Kisayollar" is a retired Turkish value; in German it is nobody's copy.
    frozenRow('de', 'admin', 'sidebar.shortcuts', 'Kisayollar');

    runFrozenCopiesMigration();

    expect(DynamicTranslation::where(['language' => 'de', 'key' => 'sidebar.shortcuts'])->value('value'))->toBe('Kisayollar');
});

test('a Turkish text the operator wrote stays', function () {
    frozenRow('tr', 'auth', 'register.address', 'Fatura Adresi');

    runFrozenCopiesMigration();

    app()->setLocale('tr');
    expect(__('auth.register.address'))->toBe('Fatura Adresi');
});

test('a copy of the current file text is removed with nothing visible changing', function () {
    $files = app(OfficialTranslationRepository::class);
    $zh = $files->forLocale('zh')['auth']['login.title'];
    $de = $files->forLocale('de')['auth']['login.title'];
    frozenRow('zh', 'auth', 'login.title', $zh);
    frozenRow('de', 'auth', 'login.title', $de);

    runFrozenCopiesMigration();

    expect(DynamicTranslation::where(['group' => 'auth', 'key' => 'login.title'])->whereIn('language', ['zh', 'de'])->count())->toBe(0);
    app()->setLocale('zh');
    expect(__('auth.login.title'))->toBe($zh);
});

test('the April English snapshot gives way to the English the files have now', function () {
    $snapshot = (new TranslationSeeder)->getTranslations();
    $files = app(OfficialTranslationRepository::class)->forLocale('en');
    $stale = null;
    foreach ($snapshot as $group => $keys) {
        foreach ($keys as $key => $value) {
            if (isset($files[$group][$key]) && $files[$group][$key] !== $value) {
                $stale = [$group, $key, $value];
                break 2;
            }
        }
    }
    expect($stale)->not->toBeNull();
    [$group, $key, $old] = $stale;
    frozenRow('en', $group, $key, $old);

    runFrozenCopiesMigration();

    app()->setLocale('en');
    expect(__($group.'.'.$key))->toBe($files[$group][$key]);
});

test('a text that only exists in the database is left alone, in any language', function () {
    frozenRow('tr', 'client', 'zz_only_here', 'Yalnız veritabanında');
    frozenRow('de', 'client', 'zz_only_here', 'Nur in der Datenbank');

    runFrozenCopiesMigration();

    expect(DynamicTranslation::where('key', 'zz_only_here')->count())->toBe(2);
});

test('a German text that differs from the file is not touched', function () {
    frozenRow('de', 'auth', 'login.title', 'Hier anmelden');

    runFrozenCopiesMigration();

    expect(DynamicTranslation::where(['language' => 'de', 'group' => 'auth', 'key' => 'login.title'])->value('value'))
        ->toBe('Hier anmelden');
});

test('the seeder no longer writes English the files already carry', function () {
    DynamicTranslation::where('language', 'en')->delete();

    $this->seed(TranslationSeeder::class);

    $files = app(OfficialTranslationRepository::class)->forLocale('en');
    $copies = DynamicTranslation::where('language', 'en')->get()
        ->filter(fn ($r) => isset($files[$r->group][$r->key]));
    expect($copies)->toHaveCount(0);
});

test('running it twice changes nothing more', function () {
    frozenRow('tr', 'auth', '2fa.backup_codes', 'Geriup Kods');
    frozenRow('tr', 'auth', 'register.address', 'Fatura Adresi');

    runFrozenCopiesMigration();
    $after = DynamicTranslation::orderBy('id')->pluck('value', 'id')->all();
    runFrozenCopiesMigration();

    expect(DynamicTranslation::orderBy('id')->pluck('value', 'id')->all())->toBe($after);
});
