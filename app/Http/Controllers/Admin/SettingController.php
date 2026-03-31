<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SettingController extends Controller
{
    public function general()
    {
        $settings = Setting::where('group', 'general')->pluck('value', 'setting');
        return view('admin.settings.general', compact('settings'));
    }

    public function updateGeneral(Request $request)
    {
        $mailKeys = [
            'MailType', 'SMTPHost', 'SMTPPort', 'SMTPUsername', 'SMTPPassword',
            'SMTPSecurity', 'SystemEmailAddress', 'EmailFromName', 'MailEnabled',
        ];

        // Handle unchecked checkboxes — they are not submitted
        $data = $request->except('_token');

        // Ensure MailEnabled is saved as 0 when unchecked
        if (!isset($data['MailEnabled'])) {
            $data['MailEnabled'] = '0';
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value, 'general');
        }

        return back()->with('success', 'Settings updated.');
    }

    public function testEmail(Request $request)
    {
        $settings = Setting::where('group', 'general')->pluck('value', 'setting')->toArray();

        $toAddress = $settings['SystemEmailAddress'] ?? null;
        if (empty($toAddress)) {
            $toAddress = $settings['Email'] ?? null;
        }
        if (empty($toAddress)) {
            return response()->json([
                'success' => false,
                'message' => 'No recipient address configured. Set System Email Address first.',
            ]);
        }

        $mailType = $settings['MailType'] ?? 'php_mail';
        $fromAddress = $settings['SystemEmailAddress'] ?? 'noreply@example.com';
        $fromName    = $settings['EmailFromName'] ?? 'PNLCS';

        // Override Laravel mail config dynamically
        if ($mailType === 'smtp') {
            $encryption = ($settings['SMTPSecurity'] ?? 'tls') === 'none' ? null : ($settings['SMTPSecurity'] ?? 'tls');
            config([
                'mail.default'                     => 'smtp',
                'mail.mailers.smtp.host'            => $settings['SMTPHost'] ?? 'localhost',
                'mail.mailers.smtp.port'            => (int)($settings['SMTPPort'] ?? 587),
                'mail.mailers.smtp.username'        => $settings['SMTPUsername'] ?? null,
                'mail.mailers.smtp.password'        => $settings['SMTPPassword'] ?? null,
                'mail.mailers.smtp.encryption'      => $encryption,
                'mail.from.address'                 => $fromAddress,
                'mail.from.name'                    => $fromName,
            ]);
        } else {
            config([
                'mail.default'          => 'sendmail',
                'mail.from.address'     => $fromAddress,
                'mail.from.name'        => $fromName,
            ]);
        }

        try {
            Mail::raw('This is a test email sent from PNLCS to verify your mail configuration is working correctly.', function ($message) use ($toAddress, $fromAddress, $fromName) {
                $message->to($toAddress)
                         ->subject('PNLCS Test Email')
                         ->from($fromAddress, $fromName);
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $toAddress,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
