<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Support\MailTransport;

/**
 * Which transport actually carries the mail.
 *
 * The settings screen offers PHP mail and SMTP, with PHP mail shown as the
 * selection when nothing has been chosen. MailConfigProvider - which governs
 * every real email - only ever acted on SMTP. Choosing PHP mail therefore did
 * nothing at all and delivery fell back to whatever MAIL_MAILER happened to
 * say, which on this installation is "log": every invoice, reminder and
 * password reset was written into storage/logs and nobody received one.
 *
 * The test-email button, meanwhile, had its own copy of the logic and did
 * handle PHP mail, so it proved nothing about live sending. The two paths now
 * share one resolver.
 */
beforeEach(function () {
    config(['mail.default' => 'log']);
});

test('choosing PHP mail sends through sendmail', function () {
    Setting::set('MailType', 'php_mail', 'general');

    expect(MailTransport::configure())->toBe('sendmail')
        ->and(config('mail.default'))->toBe('sendmail');
});

test('choosing SMTP sends through the configured server', function () {
    Setting::set('MailType', 'smtp', 'general');
    Setting::set('SMTPHost', 'smtp.example.test', 'general');
    Setting::set('SMTPPort', '2525', 'general');

    expect(MailTransport::configure())->toBe('smtp')
        ->and(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525);
});

test('choosing nothing leaves the environment alone', function () {
    Setting::where('setting', 'MailType')->delete();

    expect(MailTransport::configure())->toBe('log')
        ->and(config('mail.default'))->toBe('log');
});

test('the test button reports the transport it used', function () {
    Setting::where('setting', 'MailType')->delete();
    Setting::set('SystemEmailAddress', 'owner@example.test', 'general');

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.test-email'))
        ->assertOk();

    // The button used to switch to sendmail on its own, so it could report
    // success while nothing real was ever delivered that way.
    expect(config('mail.default'))->toBe('log')
        ->and($response->json('transport'))->toBe('log');
});

test('the sender address and name follow the settings', function () {
    Setting::set('MailType', 'php_mail', 'general');
    Setting::set('SystemEmailAddress', 'billing@example.test', 'general');
    Setting::set('EmailFromName', 'Example Hosting', 'general');

    MailTransport::configure();

    expect(config('mail.from.address'))->toBe('billing@example.test')
        ->and(config('mail.from.name'))->toBe('Example Hosting');
});
