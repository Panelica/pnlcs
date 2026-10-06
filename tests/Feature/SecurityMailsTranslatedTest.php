<?php

use App\Mail\LoginEmailChangedMail;
use App\Mail\NewDeviceLoginMail;
use App\Mail\PasswordResetMail;

/**
 * The three account-security e-mails (password reset, new-device sign-in, the
 * sign-in address changed) were written in English in the view and the
 * mailable, with no translation keys, so a Turkish or German customer got
 * them in English while every other e-mail came in their language. They now
 * read email.password_reset.*, email.new_device_login.* and
 * email.login_email_changed.*, and the mailables already pick the
 * recipient's language.
 */
function securityMails(): array
{
    return [
        'reset' => fn () => new PasswordResetMail('https://example.test/reset?token=t', 'mail-tr@example.test'),
        'device' => fn () => new NewDeviceLoginMail('mail-tr@example.test', '203.0.113.9', 'Firefox on Linux', '2026-10-06 12:00'),
        'changed' => fn () => new LoginEmailChangedMail('old-tr@example.test', 'mail-tr@example.test'),
    ];
}

test('the security e-mails come in the customer\'s language, subject and body', function () {
    \App\Models\Client::factory()->create(['email' => 'mail-tr@example.test', 'language' => 'tr']);

    $expect = [
        'reset' => [__('email.password_reset.subject', ['company' => company_name()], 'tr'), __('email.password_reset.action', [], 'tr')],
        'device' => [__('email.new_device_login.subject', ['company' => company_name()], 'tr'), __('email.new_device_login.intro', [], 'tr')],
        'changed' => [__('email.login_email_changed.subject', ['company' => company_name()], 'tr'), __('email.login_email_changed.how', [], 'tr')],
    ];

    foreach (securityMails() as $name => $make) {
        $mail = $make();
        [$subject, $line] = $expect[$name];
        expect($subject)->not->toContain('email.')
            ->and($mail->locale)->toBe('tr', $name.' picks the recipient\'s language');

        $mail->assertHasSubject($subject);
        $mail->assertSeeInHtml($line, false);
    }
});

test('in English they say what they said before', function () {
    app()->setLocale('en');

    $reset = (new PasswordResetMail('https://example.test/reset?token=t', 'nobody@example.test'));
    $reset->assertHasSubject('Reset your '.company_name().' password');
    $reset->assertSeeInHtml('We received a request to reset the password for your account (nobody@example.test).', false);
    $reset->assertSeeInHtml('Reset password');

    $device = new NewDeviceLoginMail('nobody@example.test', '203.0.113.9', 'Firefox on Linux', '2026-10-06 12:00');
    $device->assertHasSubject('New sign-in to your '.company_name().' account');
    $device->assertSeeInHtml('If this was you, there is nothing to do.');

    $changed = new LoginEmailChangedMail('old@example.test', 'nobody@example.test');
    $changed->assertHasSubject('The sign-in address on your '.company_name().' account was changed');
    $changed->assertSeeInHtml('has been changed from old@example.test to nobody@example.test.', false);
});

test('every full language has the keys', function () {
    foreach (['en', 'tr', 'de', 'pl', 'zh'] as $locale) {
        foreach (['email.password_reset.subject', 'email.password_reset.ignore', 'email.new_device_login.not_you', 'email.login_email_changed.not_you'] as $key) {
            expect(\Illuminate\Support\Facades\Lang::hasForLocale($key, $locale))->toBeTrue("{$locale}: {$key}");
        }
    }
});
