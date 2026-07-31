<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Which transport carries the mail, decided in one place.
 *
 * The settings screen offers PHP mail and SMTP. MailConfigProvider - which
 * governs every real email - only ever acted on SMTP, so choosing PHP mail did
 * nothing and delivery fell back to whatever MAIL_MAILER said. The test-email
 * button had its own copy of the logic and did handle PHP mail, so a
 * successful test proved nothing about live sending. Both now call this.
 */
class MailTransport
{
    /**
     * Apply the mail settings and return the transport now in force.
     *
     * An operator who has never chosen keeps whatever the environment
     * configures: MAIL_MAILER may name a service the panel knows nothing about,
     * and turning delivery on for them is not ours to decide.
     */
    public static function configure(): string
    {
        try {
            $settings = Setting::where('group', 'general')->pluck('value', 'setting')->toArray();
        } catch (\Throwable) {
            // Database not ready — installer, or a broken connection.
            return (string) config('mail.default');
        }

        $type = $settings['MailType'] ?? null;

        if ($type === 'smtp') {
            $security = $settings['SMTPSecurity'] ?? 'tls';

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $settings['SMTPHost'] ?? 'localhost',
                'mail.mailers.smtp.port' => (int) ($settings['SMTPPort'] ?? 587),
                'mail.mailers.smtp.username' => $settings['SMTPUsername'] ?? null,
                'mail.mailers.smtp.password' => $settings['SMTPPassword'] ?? null,
                'mail.mailers.smtp.encryption' => $security === 'none' ? null : $security,
            ]);
        } elseif ($type === 'php_mail') {
            config(['mail.default' => 'sendmail']);
        }

        if (! empty($settings['SystemEmailAddress'])) {
            config(['mail.from.address' => $settings['SystemEmailAddress']]);
        }

        if (! empty($settings['EmailFromName'])) {
            config(['mail.from.name' => $settings['EmailFromName']]);
        }

        return (string) config('mail.default');
    }
}
