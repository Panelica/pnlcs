<?php

use App\Casts\SecretSettingValue;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/*
 * Credentials in the settings table are encrypted at rest.
 *
 * Gateway, addon and server credentials already were; the general settings
 * kept the mail password, the Google/GitHub client secrets, the reCAPTCHA
 * secret and the OpenAI, Twilio, FraudLabs and MaxMind keys in plain text,
 * so a copy of the database (a backup, a dump sent for support) handed them
 * over. They are now encrypted; everything that reads them gets the value.
 */
test('a credential setting is stored encrypted and read back as it was', function () {
    Setting::set('SMTPPassword', 'mail-pass-123');

    $raw = DB::table('settings')->where('setting', 'SMTPPassword')->value('value');
    expect($raw)->not->toBe('mail-pass-123')
        ->and(Crypt::decryptString(substr($raw, strlen(SecretSettingValue::PREFIX))))->toBe('mail-pass-123')
        ->and(Setting::get('SMTPPassword'))->toBe('mail-pass-123');
});

test('every listed credential is encrypted, and other settings are not', function () {
    foreach (Setting::SECRETS as $name) {
        Setting::set($name, 'value-of-'.$name);
        expect(DB::table('settings')->where('setting', $name)->value('value'))->not->toBe('value-of-'.$name);
    }

    Setting::set('CompanyName', 'Example Hosting');
    Setting::set('RecaptchaSiteKey', 'public-site-key');
    expect(DB::table('settings')->where('setting', 'CompanyName')->value('value'))->toBe('Example Hosting')
        ->and(DB::table('settings')->where('setting', 'RecaptchaSiteKey')->value('value'))->toBe('public-site-key');
});

test('readers that pluck the settings get the plain value', function () {
    Setting::set('SMTPPassword', 'mail-pass-123', 'general');

    expect(Setting::where('group', 'general')->pluck('value', 'setting')['SMTPPassword'])->toBe('mail-pass-123');
});

test('a credential stored in plain text before this still reads, and the migration encrypts it', function () {
    DB::table('settings')->updateOrInsert(['setting' => 'GoogleClientSecret'], ['value' => 'old-plain-secret', 'group' => 'general']);
    expect(Setting::get('GoogleClientSecret'))->toBe('old-plain-secret');

    $migration = require database_path('migrations/2026_11_02_000001_encrypt_credential_settings.php');
    $migration->up();
    $migration->up(); // twice changes nothing

    $raw = DB::table('settings')->where('setting', 'GoogleClientSecret')->value('value');
    expect($raw)->not->toBe('old-plain-secret')
        ->and(Crypt::decryptString(substr($raw, strlen(SecretSettingValue::PREFIX))))->toBe('old-plain-secret')
        ->and(Setting::get('GoogleClientSecret'))->toBe('old-plain-secret');

    $migration->down();
    expect(DB::table('settings')->where('setting', 'GoogleClientSecret')->value('value'))->toBe('old-plain-secret');
});

test('a module that encrypts its own value gets back what it saved', function () {
    $own = Crypt::encryptString('partner-key');
    Setting::set('SomeModuleKey', $own);

    expect(Setting::get('SomeModuleKey'))->toBe($own);
});
