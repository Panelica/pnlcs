<?php
namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;

class MailConfigProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $settings = Setting::where('group', 'general')->pluck('value', 'setting')->toArray();

            if (($settings['MailType'] ?? '') === 'smtp') {
                $encryption = ($settings['SMTPSecurity'] ?? 'tls') === 'none'
                    ? null
                    : ($settings['SMTPSecurity'] ?? 'tls');

                config([
                    'mail.default'                => 'smtp',
                    'mail.mailers.smtp.host'       => $settings['SMTPHost'] ?? 'localhost',
                    'mail.mailers.smtp.port'       => (int)($settings['SMTPPort'] ?? 587),
                    'mail.mailers.smtp.username'   => $settings['SMTPUsername'] ?? null,
                    'mail.mailers.smtp.password'   => $settings['SMTPPassword'] ?? null,
                    'mail.mailers.smtp.encryption' => $encryption,
                ]);
            }

            if (!empty($settings['SystemEmailAddress'])) {
                config(['mail.from.address' => $settings['SystemEmailAddress']]);
            }

            if (!empty($settings['EmailFromName'])) {
                config(['mail.from.name' => $settings['EmailFromName']]);
            }
        } catch (\Throwable $e) {
            // DB not ready yet — silently skip
        }
    }
}
